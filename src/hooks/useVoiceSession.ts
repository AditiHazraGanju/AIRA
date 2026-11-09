"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { RELAY_WS_URL } from "@/const/conf";
import { listSessions } from "@/services/sessions";

// Talks to healthtalk-relay. Protocol: healthtalk-relay/README.md.
//   up:   {"type":"start"} first, then PCM16 16 kHz mic frames, audio_end, text, end
//   down: ready, PCM16 24 kHz audio frames, transcript deltas, interrupted,
//         turn_complete, error

export type VoiceStatus = "idle" | "connecting" | "ready";

export interface ChatMessage {
  id: string;
  role: "user" | "assistant";
  text: string;
  interrupted?: boolean;
}

export type VoiceNotice =
  | "connection_failed"
  | "mic_denied"
  | "no_response"
  | "session_replaced"
  | "unauthorized";

interface Options {
  /** API language key: ENG | HIN | MAR | BEN */
  language: string;
  onNotice?: (notice: VoiceNotice) => void;
  onUnauthorized?: () => void;
}

const OUTPUT_SAMPLE_RATE = 24000;
const WORKLET_URL = "/worklets/pcm-capture.js";
// Up to 15 s of 16 kHz PCM16 held while the session opens (measured 1-6 s).
const MAX_PENDING_MIC_BYTES = 16000 * 2 * 15;

// If Tara hears herself through the speaker and keeps cutting herself off on
// some phones, set this to true: the mic is muted while she talks (the user
// can still interrupt by tapping the mic button).
const MUTE_MIC_WHILE_SPEAKING = false;

let messageCounter = 0;
const newId = () => `m${Date.now()}-${messageCounter++}`;

export function useVoiceSession({ language, onNotice, onUnauthorized }: Options) {
  const [status, setStatus] = useState<VoiceStatus>("idle");
  const [micActive, setMicActive] = useState(false);
  const [speaking, setSpeaking] = useState(false);
  const [awaitingReply, setAwaitingReply] = useState(false);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  // The relay's session id, which is also the saved conversation's id (ht_sessions).
  const sessionIdRef = useRef<string | null>(null);

  const wsRef = useRef<WebSocket | null>(null);
  const readyPromiseRef = useRef<Promise<void> | null>(null);
  const sessionLanguageRef = useRef<string | null>(null);

  const audioCtxRef = useRef<AudioContext | null>(null);
  const workletLoadedRef = useRef(false);
  const micStreamRef = useRef<MediaStream | null>(null);
  const micNodesRef = useRef<AudioNode[]>([]);
  // True once capture is running (frames are sent/buffered only then).
  const micActiveRef = useRef(false);
  // True from the tap that turns the mic on until it's turned off, including
  // the seconds spent starting up, so a second tap during start-up stops it.
  const micWantedRef = useRef(false);
  // Bumped on every start and release; a start-up that finds it changed was cancelled.
  const micAttemptRef = useRef(0);
  // Mic frames captured before the session is ready, sent once it is.
  const pendingMicRef = useRef<ArrayBuffer[]>([]);
  const pendingMicBytesRef = useRef(0);
  // The mic was turned off before the session was ready: send audio_end after the buffered frames.
  const audioEndPendingRef = useRef(false);
  const isReadyRef = useRef(false);

  const playingSourcesRef = useRef(new Set<AudioBufferSourceNode>());
  const nextPlayTimeRef = useRef(0);

  // The bubbles currently being filled by streaming transcript deltas.
  const currentUserIdRef = useRef<string | null>(null);
  const currentAssistantIdRef = useRef<string | null>(null);

  // Keep callbacks current without re-creating the socket handlers.
  const onNoticeRef = useRef(onNotice);
  const onUnauthorizedRef = useRef(onUnauthorized);
  useEffect(() => {
    onNoticeRef.current = onNotice;
    onUnauthorizedRef.current = onUnauthorized;
  });

  // ── Audio context (must be created/resumed inside a user gesture) ───────

  const ensureAudioContext = useCallback(async () => {
    if (!audioCtxRef.current) {
      audioCtxRef.current = new AudioContext();
    }
    if (audioCtxRef.current.state === "suspended") {
      await audioCtxRef.current.resume();
    }
    return audioCtxRef.current;
  }, []);

  // ── Playback ────────────────────────────────────────────────────────────

  const flushPlayback = useCallback(() => {
    for (const source of playingSourcesRef.current) {
      source.onended = null;
      try {
        source.stop();
      } catch {
        // already stopped
      }
    }
    playingSourcesRef.current.clear();
    nextPlayTimeRef.current = 0;
    setSpeaking(false);
  }, []);

  const playChunk = useCallback((data: ArrayBuffer) => {
    const ctx = audioCtxRef.current;
    if (!ctx) return;

    const pcm = new Int16Array(data);
    const buffer = ctx.createBuffer(1, pcm.length, OUTPUT_SAMPLE_RATE);
    const channel = buffer.getChannelData(0);
    for (let i = 0; i < pcm.length; i++) channel[i] = pcm[i] / 0x8000;

    const source = ctx.createBufferSource();
    source.buffer = buffer;
    source.connect(ctx.destination);

    // Schedule back-to-back so chunks play gaplessly.
    const startAt = Math.max(ctx.currentTime + 0.02, nextPlayTimeRef.current);
    source.start(startAt);
    nextPlayTimeRef.current = startAt + buffer.duration;

    playingSourcesRef.current.add(source);
    setSpeaking(true);
    source.onended = () => {
      playingSourcesRef.current.delete(source);
      if (playingSourcesRef.current.size === 0) setSpeaking(false);
    };
  }, []);

  // ── Transcript bubbles ──────────────────────────────────────────────────

  const appendDelta = useCallback((role: "user" | "assistant", delta: string) => {
    // Refs are read and written outside the state updaters: React may run an
    // updater twice (StrictMode), and a side effect inside would drop text.
    const idRef = role === "user" ? currentUserIdRef : currentAssistantIdRef;
    const existingId = idRef.current;
    if (existingId) {
      setMessages((prev) => prev.map((m) => (m.id === existingId ? { ...m, text: m.text + delta } : m)));
      return;
    }

    const msg: ChatMessage = { id: newId(), role, text: delta };
    idRef.current = msg.id;
    const assistantId = currentAssistantIdRef.current;

    setMessages((prev) => {
      // Transcripts aren't ordered relative to Tara's reply: if her bubble
      // for this turn already exists, the user's words belong before it.
      if (role === "user" && assistantId) {
        const at = prev.findIndex((m) => m.id === assistantId);
        if (at >= 0) return [...prev.slice(0, at), msg, ...prev.slice(at)];
      }
      return [...prev, msg];
    });
  }, []);

  // ── Mic ─────────────────────────────────────────────────────────────────

  /**
   * Turns the mic off and cancels a start-up in progress. Buffered frames are
   * dropped unless keepPending (the user stopped talking while the session
   * was still connecting: they're sent once it's ready).
   */
  const releaseMic = useCallback((keepPending = false) => {
    micAttemptRef.current++;
    micWantedRef.current = false;
    micActiveRef.current = false;
    setMicActive(false);
    micNodesRef.current.forEach((node) => node.disconnect());
    micNodesRef.current = [];
    micStreamRef.current?.getTracks().forEach((track) => track.stop());
    micStreamRef.current = null;
    if (!keepPending) {
      pendingMicRef.current = [];
      pendingMicBytesRef.current = 0;
      audioEndPendingRef.current = false;
    }
  }, []);

  const flushPendingMic = useCallback(() => {
    const ws = wsRef.current;
    if (!isReadyRef.current || ws?.readyState !== WebSocket.OPEN) return;
    for (const frame of pendingMicRef.current) ws.send(frame);
    pendingMicRef.current = [];
    pendingMicBytesRef.current = 0;
    if (audioEndPendingRef.current) {
      audioEndPendingRef.current = false;
      ws.send(JSON.stringify({ type: "audio_end" }));
    }
  }, []);

  // ── Connection ──────────────────────────────────────────────────────────

  const handleServerMessage = useCallback(
    (event: MessageEvent) => {
      if (event.data instanceof ArrayBuffer) {
        setAwaitingReply(false);
        playChunk(event.data);
        return;
      }

      let msg: { type: string; role?: "user" | "assistant"; delta?: string; code?: string };
      try {
        msg = JSON.parse(event.data);
      } catch {
        return;
      }

      switch (msg.type) {
        case "transcript":
          if (msg.role && msg.delta) appendDelta(msg.role, msg.delta);
          break;
        case "interrupted": {
          flushPlayback();
          const cutId = currentAssistantIdRef.current;
          if (cutId) {
            setMessages((prev) => prev.map((m) => (m.id === cutId ? { ...m, interrupted: true } : m)));
          }
          currentAssistantIdRef.current = null;
          break;
        }
        case "turn_complete":
          currentUserIdRef.current = null;
          currentAssistantIdRef.current = null;
          setAwaitingReply(false);
          break;
        case "error":
          if (msg.code === "no_response") {
            setAwaitingReply(false);
            onNoticeRef.current?.("no_response");
          }
          break;
      }
    },
    [appendDelta, flushPlayback, playChunk]
  );

  /**
   * The relay refused our token (close code 4003). Only log the user out if
   * the API agrees the token is invalid. If the API still accepts it, the
   * relay is misconfigured (e.g. its JWT_SECRET differs from the API's) and
   * logging out would just bounce every user back to the login page.
   */
  const handleRelayRejectedToken = useCallback(async () => {
    try {
      await listSessions(1);
      console.error(
        "Tara relay rejected a token the API accepts. Check that the relay's JWT_SECRET matches the API's."
      );
      onNoticeRef.current?.("connection_failed");
    } catch (err) {
      if (axios.isAxiosError(err) && err.response?.status === 401) {
        onNoticeRef.current?.("unauthorized");
        onUnauthorizedRef.current?.();
      } else {
        onNoticeRef.current?.("connection_failed");
      }
    }
  }, []);

  const connect = useCallback((): Promise<void> => {
    // Reuse a socket that is open or still connecting (e.g. a double tap).
    const state = wsRef.current?.readyState;
    const usable = state === WebSocket.OPEN || state === WebSocket.CONNECTING;
    if (readyPromiseRef.current && usable && sessionLanguageRef.current === language) {
      return readyPromiseRef.current;
    }

    const token = localStorage.getItem("auth_token") || localStorage.getItem("access_token");
    if (!token) {
      onUnauthorizedRef.current?.();
      return Promise.reject(new Error("not logged in"));
    }

    setStatus("connecting");
    const ws = new WebSocket(RELAY_WS_URL);
    ws.binaryType = "arraybuffer";
    wsRef.current = ws;
    sessionLanguageRef.current = language;

    const ready = new Promise<void>((resolve, reject) => {
      let isReady = false;

      ws.onopen = () => ws.send(JSON.stringify({ type: "start", token, language }));

      ws.onmessage = (event) => {
        const ready = !isReady && typeof event.data === "string" ? JSON.parse(event.data) : null;
        if (ready?.type === "ready") {
          isReady = true;
          isReadyRef.current = true;
          sessionIdRef.current = ready.sessionId ?? null;
          setStatus("ready");
          // Speech captured while connecting, and audio_end if the mic is already off.
          flushPendingMic();
          resolve();
          return;
        }
        handleServerMessage(event);
      };

      ws.onclose = (event) => {
        if (wsRef.current !== ws) return; // an older socket we already replaced
        wsRef.current = null;
        readyPromiseRef.current = null;
        isReadyRef.current = false;
        setStatus("idle");
        setAwaitingReply(false);
        releaseMic();
        currentUserIdRef.current = null;
        currentAssistantIdRef.current = null;

        if (event.code === 4003) {
          void handleRelayRejectedToken();
        } else if (event.code === 4009) {
          onNoticeRef.current?.("session_replaced");
        } else if (!isReady || event.code === 1011 || event.code === 1013) {
          onNoticeRef.current?.("connection_failed");
        }
        // 1000 / 4008 idle / 4010 max length: quiet. The next tap reconnects.

        if (!isReady) reject(new Error(`relay closed: ${event.code} ${event.reason}`));
      };
    });

    readyPromiseRef.current = ready;
    return ready;
  }, [flushPendingMic, handleRelayRejectedToken, handleServerMessage, language, releaseMic]);

  const endSession = useCallback(() => {
    const ws = wsRef.current;
    wsRef.current = null;
    readyPromiseRef.current = null;
    isReadyRef.current = false;
    if (ws?.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ type: "end" }));
      ws.close(1000);
    } else {
      ws?.close();
    }
    releaseMic();
    flushPlayback();
    setStatus("idle");
    setAwaitingReply(false);
    currentUserIdRef.current = null;
    currentAssistantIdRef.current = null;
  }, [flushPlayback, releaseMic]);

  // ── Public actions ──────────────────────────────────────────────────────

  const startMic = useCallback(async () => {
    const attempt = ++micAttemptRef.current;
    const cancelled = () => micAttemptRef.current !== attempt; // stopped (or restarted) meanwhile
    micWantedRef.current = true;
    setMicActive(true); // show it as on right away: start-up can take seconds

    let stream: MediaStream | null = null;
    try {
      const ctx = await ensureAudioContext(); // inside the tap gesture
      if (cancelled()) return;
      // Start connecting now, but capture immediately: the user starts
      // talking right after tapping, and opening the session takes seconds.
      const connecting = connect();
      connecting.catch(() => {}); // handled below; avoid an unhandled rejection meanwhile

      stream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
      });
      if (cancelled()) {
        stream.getTracks().forEach((track) => track.stop());
        return;
      }

      if (!workletLoadedRef.current) {
        await ctx.audioWorklet.addModule(WORKLET_URL);
        workletLoadedRef.current = true;
      }
      if (cancelled()) {
        stream.getTracks().forEach((track) => track.stop());
        return;
      }

      const source = ctx.createMediaStreamSource(stream);
      const capture = new AudioWorkletNode(ctx, "pcm-capture");
      // A muted sink keeps the worklet pulled by the graph on every browser.
      const sink = ctx.createGain();
      sink.gain.value = 0;
      source.connect(capture).connect(sink).connect(ctx.destination);

      capture.port.onmessage = (event: MessageEvent<ArrayBuffer>) => {
        if (!micActiveRef.current) return;
        if (MUTE_MIC_WHILE_SPEAKING && playingSourcesRef.current.size > 0) return;

        const ws = wsRef.current;
        if (isReadyRef.current && ws?.readyState === WebSocket.OPEN) {
          flushPendingMic();
          ws.send(event.data);
        } else if (pendingMicBytesRef.current + event.data.byteLength <= MAX_PENDING_MIC_BYTES) {
          pendingMicRef.current.push(event.data);
          pendingMicBytesRef.current += event.data.byteLength;
        }
      };

      micStreamRef.current = stream;
      micNodesRef.current = [source, capture, sink];
      micActiveRef.current = true;

      await connecting; // the ready handler sends what was buffered meanwhile
    } catch (err) {
      // A stream we got but never handed to micStreamRef isn't released by releaseMic.
      if (stream && micStreamRef.current !== stream) stream.getTracks().forEach((track) => track.stop());
      if (cancelled()) return; // already turned off; don't touch a newer start
      releaseMic();
      if (err instanceof DOMException && (err.name === "NotAllowedError" || err.name === "SecurityError")) {
        onNoticeRef.current?.("mic_denied");
      }
      // connection failures are reported by onclose
    }
  }, [connect, ensureAudioContext, flushPendingMic, releaseMic]);

  const stopMic = useCallback(() => {
    if (!micWantedRef.current) return;
    const wasCapturing = micActiveRef.current;
    const ws = wsRef.current;
    const ready = isReadyRef.current && ws?.readyState === WebSocket.OPEN;
    // Still connecting: keep what the user said and send it, then audio_end, once ready.
    const keepPending = wasCapturing && !ready && pendingMicRef.current.length > 0;
    releaseMic(keepPending);
    if (keepPending) {
      audioEndPendingRef.current = true;
    } else if (wasCapturing && ready && ws) {
      ws.send(JSON.stringify({ type: "audio_end" }));
      if (currentUserIdRef.current) setAwaitingReply(true);
    }
  }, [releaseMic]);

  // micWantedRef, not micActiveRef: a tap while the mic is still starting up stops it.
  const toggleMic = useCallback(() => {
    if (micWantedRef.current) stopMic();
    else void startMic();
  }, [startMic, stopMic]);

  const sendText = useCallback(
    async (text: string) => {
      const trimmed = text.trim();
      if (!trimmed) return;
      try {
        await ensureAudioContext(); // inside the send gesture, so Tara can be heard
        await connect();
      } catch {
        return;
      }
      // A typed question replaces whatever Tara is saying.
      flushPlayback();
      currentUserIdRef.current = null;
      currentAssistantIdRef.current = null;
      setMessages((prev) => [...prev, { id: newId(), role: "user", text: trimmed }]);
      setAwaitingReply(true);
      wsRef.current?.send(JSON.stringify({ type: "text", text: trimmed }));
    },
    [connect, ensureAudioContext, flushPlayback]
  );

  /**
   * The user ended the conversation: close it (the relay saves it and the
   * API summarises it) and clear the screen for a fresh one. Returns the
   * session id for the rating, or null if the user never said anything,
   * since then nothing was saved and there is nothing to rate.
   */
  const finishConversation = useCallback((): string | null => {
    const sessionId = sessionIdRef.current;
    const userSpoke = messages.some((m) => m.role === "user");
    endSession();
    sessionIdRef.current = null;
    setMessages([]);
    return userSpoke ? sessionId : null;
  }, [endSession, messages]);

  /** Silence Tara and the mic, e.g. when the Start Pitch audio begins. */
  const pause = useCallback(() => {
    stopMic();
    flushPlayback();
  }, [flushPlayback, stopMic]);

  // Gemini fixes the reply language for a whole session, so a language
  // change ends it; the next tap opens a new one in the new language.
  useEffect(() => {
    if (sessionLanguageRef.current && sessionLanguageRef.current !== language && wsRef.current) {
      endSession();
    }
  }, [language, endSession]);

  // Tear everything down when the page unmounts.
  useEffect(() => {
    return () => {
      endSession();
      void audioCtxRef.current?.close();
      audioCtxRef.current = null;
      workletLoadedRef.current = false;
    };
  }, [endSession]);

  return {
    status,
    micActive,
    speaking,
    awaitingReply,
    messages,
    toggleMic,
    sendText,
    pause,
    endSession,
    finishConversation,
  };
}
