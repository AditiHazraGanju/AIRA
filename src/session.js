import { randomUUID } from "node:crypto";
import { Modality } from "@google/genai";
import { config } from "./config.js";
import { logger } from "./logger.js";
import { buildSystemInstruction } from "./prompt.js";
import { SEARCH_TOOL, searchContentLibrary } from "./knowledge.js";
import { SessionStore } from "./persistence.js";

// While Gemini is (re)connecting, hold at most this much mic audio so the
// start of what the user says isn't lost. ~2s of 16kHz 16-bit mono.
const MAX_PENDING_AUDIO_BYTES = config.inputSampleRate * 2 * 2;
const MAX_TEXT_LENGTH = 2000;
// 1s of PCM16 silence, sent on audio_end so Gemini's VAD detects end of speech.
const TRAILING_SILENCE = Buffer.alloc(config.inputSampleRate * 2);
// How long to wait for the first reply audio after the user finishes.
// Normal first-audio latency measured at 0.7-1.1s.
const RESPONSE_TIMEOUT_MS = 8000;

export const CloseCode = {
  BAD_REQUEST: 4000,
  START_TIMEOUT: 4001,
  UNAUTHORIZED: 4003,
  IDLE_TIMEOUT: 4008,
  REPLACED: 4009,
  MAX_DURATION: 4010,
  UPSTREAM_ERROR: 1011,
  BUSY: 1013,
};

/**
 * One chatbot conversation: a browser WebSocket on one side, a Gemini Live
 * session on the other. Owns transcripts, barge-in signalling, timers and
 * transparent reconnects to Gemini via session resumption.
 */
export class RelaySession {
  constructor({ ws, ai, userId, token, language, guardrails, onEnd }) {
    this.id = randomUUID();
    this.ws = ws;
    this.ai = ai;
    this.userId = userId;
    this.token = token; // the user's JWT, for Content Library searches via Laravel
    this.language = language;
    this.systemInstruction = buildSystemInstruction({ language, guardrails });
    this.onEnd = onEnd;

    this.gemini = null;
    this.geminiReady = false;
    this.resumeHandle = null;
    this.reconnectAttempts = 0;
    this.pendingAudio = [];
    this.pendingAudioBytes = 0;
    this.ended = false;

    // Transcript of the conversation, for logging now and persistence later.
    this.history = [];
    this.currentUserText = "";
    this.currentAssistantText = "";
    this.turnStartedAt = null; // set when the client explicitly ends its turn
    this.firstAudioLogged = false;
    this.turnHadOutput = false;
    this.nudgedThisTurn = false;
    this.awaitingInterruptedTurnComplete = false;
    this.toolCallsInFlight = new Set();
    this.cancelledToolCalls = new Set();
    this.awaitingToolAnswer = false;
    this.currentUserMode = null; // "voice" | "text"
    this.turnUserInput = false; // the user said or typed something this turn

    // Saves the conversation for Session History as it happens.
    this.store = new SessionStore({
      token,
      sessionId: this.id,
      language,
      log: (level, message, data) => this.log(level, message, data),
    });
    this.persistedCount = 0;

    this.startedAt = Date.now();
    this.maxDurationTimer = setTimeout(
      () => this.end(CloseCode.MAX_DURATION, "max session duration reached"),
      config.maxSessionMs
    );
    this.resetIdleTimer();

    // Attach immediately so nothing the client sends before Gemini is ready is lost.
    ws.on("message", (data, isBinary) => this.handleClientMessage(data, isBinary));
    ws.on("close", () => this.end(null, "client disconnected"));
  }

  log(level, message, data = {}) {
    logger[level](message, { session: this.id, user: this.userId, ...data });
  }

  // ── Gemini side ─────────────────────────────────────────────────────────

  async start() {
    await this.connectGemini();
    this.send({
      type: "ready",
      sessionId: this.id,
      language: this.language,
      inputSampleRate: config.inputSampleRate,
      outputSampleRate: config.outputSampleRate,
    });
    this.log("info", "session started", { language: this.language });
  }

  async connectGemini() {
    this.geminiReady = false;
    const resuming = Boolean(this.resumeHandle);

    const liveConfig = {
      responseModalities: [Modality.AUDIO],
      systemInstruction: this.systemInstruction,
      inputAudioTranscription: {},
      outputAudioTranscription: {},
      tools: [SEARCH_TOOL],
      // No realtimeInputConfig: setting automaticActivityDetection.silenceDurationMs
      // (any value) makes gemini-live-2.5-flash-native-audio silently never
      // reply. Default VAD is what the latency spike measured.
      // Lets audio sessions run past the default context limit.
      contextWindowCompression: { slidingWindow: {} },
      // Empty config = "send me resumption handles"; a handle = resume.
      sessionResumption: resuming ? { handle: this.resumeHandle } : {},
    };
    if (config.voice) {
      liveConfig.speechConfig = { voiceConfig: { prebuiltVoiceConfig: { voiceName: config.voice } } };
    }

    // The server can accept the socket and then close it during setup (e.g.
    // model not available), in which case connect() never resolves. Race it
    // against an early close and a timeout so start() always settles.
    let rejectEarly;
    const closedEarly = new Promise((_, reject) => (rejectEarly = reject));
    let settled = false;

    const connecting = this.ai.live.connect({
      model: config.model,
      config: liveConfig,
      callbacks: {
        onmessage: (msg) => this.handleGeminiMessage(msg),
        onerror: (e) => this.log("warn", "gemini socket error", { error: e?.message }),
        onclose: (e) => {
          if (!settled) {
            rejectEarly(new Error(`Gemini closed during setup: ${e?.reason || e?.code}`));
            return;
          }
          this.handleGeminiClose(e);
        },
      },
    });
    const timeout = new Promise((_, reject) =>
      setTimeout(() => reject(new Error("Gemini connect timed out")), 15000)
    );

    try {
      this.gemini = await Promise.race([connecting, closedEarly, timeout]);
    } finally {
      settled = true;
    }

    this.geminiReady = true;
    this.reconnectAttempts = 0;
    this.flushPendingAudio();
    this.log("info", resuming ? "gemini session resumed" : "gemini session opened");
  }

  async handleGeminiClose(e) {
    this.geminiReady = false;
    this.gemini = null;
    if (this.ended) return;

    const reason = e?.reason || `code ${e?.code}`;
    if (this.resumeHandle && this.reconnectAttempts < config.maxReconnectAttempts) {
      this.reconnectAttempts += 1;
      this.log("warn", "gemini closed, resuming", { reason, attempt: this.reconnectAttempts });
      try {
        await this.connectGemini();
        return;
      } catch (err) {
        this.log("error", "gemini resume failed", { error: err.message });
      }
    }

    this.log("error", "gemini closed, ending session", { reason });
    this.send({ type: "error", code: "upstream_closed", message: "The voice service disconnected." });
    this.end(CloseCode.UPSTREAM_ERROR, "upstream closed");
  }

  handleGeminiMessage(msg) {
    this.log("debug", "gemini message", {
      keys: Object.keys(msg),
      serverContent: msg.serverContent ? Object.keys(msg.serverContent) : undefined,
    });
    if (msg.sessionResumptionUpdate?.resumable && msg.sessionResumptionUpdate.newHandle) {
      this.resumeHandle = msg.sessionResumptionUpdate.newHandle;
    }
    if (msg.goAway) {
      // Gemini will close soon; handleGeminiClose resumes with the latest handle.
      this.log("info", "gemini goAway received", { timeLeft: msg.goAway.timeLeft });
    }
    if (msg.toolCall) {
      this.handleToolCall(msg.toolCall);
    }
    if (msg.toolCallCancellation?.ids) {
      // The user spoke over Aira mid-lookup; Gemini no longer wants these.
      for (const id of msg.toolCallCancellation.ids) this.cancelledToolCalls.add(id);
    }

    const sc = msg.serverContent;
    if (!sc) return;

    if (sc.interrupted) {
      // Barge-in: the user spoke over Aira. The client must drop queued audio.
      // Only Aira's cut-off reply is closed out here; the user's new words
      // already belong to the next turn.
      this.flushAssistantText({ interrupted: true });
      this.persist();
      this.turnHadOutput = false;
      this.firstAudioLogged = false;
      this.awaitingToolAnswer = false; // Gemini cancels any lookup it was waiting on
      this.awaitingInterruptedTurnComplete = true;
      this.send({ type: "interrupted" });
    }

    if (sc.inputTranscription?.text) {
      this.currentUserText += sc.inputTranscription.text;
      this.currentUserMode ??= "voice";
      this.turnUserInput = true;
      this.send({ type: "transcript", role: "user", delta: sc.inputTranscription.text });
      // Open-mic mode never sends audio_end, so the user's own words are the
      // signal that a reply is owed. Re-armed on every chunk.
      if (!this.turnHadOutput) this.armResponseWatchdog();
    }

    for (const part of sc.modelTurn?.parts ?? []) {
      if (!part.inlineData?.data) continue;
      // Once Aira answers, the user's question is complete: close it now so
      // words spoken over her start a new message instead of merging in.
      this.flushUserText();
      this.turnHadOutput = true;
      this.awaitingToolAnswer = false;
      clearTimeout(this.responseWatchdog);
      if (!this.firstAudioLogged) {
        this.firstAudioLogged = true;
        if (this.turnStartedAt) {
          this.log("info", "first audio", { latencyMs: Date.now() - this.turnStartedAt });
        }
      }
      this.sendBinary(Buffer.from(part.inlineData.data, "base64"));
    }

    if (sc.outputTranscription?.text) {
      this.flushUserText();
      this.currentAssistantText += sc.outputTranscription.text;
      this.send({ type: "transcript", role: "assistant", delta: sc.outputTranscription.text });
    }

    if (sc.turnComplete) {
      // Gemini closes an interrupted turn with interrupted -> turnComplete.
      // That turnComplete belongs to the cut-off reply, not to the user's new
      // turn, so it must not close or nudge the new turn.
      if (this.awaitingInterruptedTurnComplete && !this.turnHadOutput) {
        this.awaitingInterruptedTurnComplete = false;
        return;
      }
      this.awaitingInterruptedTurnComplete = false;
      // A turn that ends in a tool call also sends turnComplete, with no
      // audio. The real answer is a new generation after the tool response,
      // with its own turnComplete. This one is neither empty nor final.
      if (!this.turnHadOutput && (this.awaitingToolAnswer || this.toolCallsInFlight.size > 0)) {
        return;
      }
      if (!this.turnHadOutput && this.turnUserInput) {
        if (!this.nudgedThisTurn) {
          this.nudge("empty turn");
          return; // the client keeps waiting; the retry's turn_complete closes the turn
        }
        this.log("warn", "retry was empty too, releasing client");
        this.send({ type: "error", code: "no_response", message: "Aira didn't catch that. Please try again." });
      }
      this.completeTurn();
    }
  }

  // ── Content Library search (Gemini tool calls) ──────────────────────────

  async handleToolCall(toolCall) {
    // A lookup legitimately delays the first reply audio; don't let the
    // silent-reply watchdog fire while one is in flight.
    clearTimeout(this.responseWatchdog);
    this.awaitingToolAnswer = true; // cleared by the answer's first audio or finishTurn

    await Promise.all(
      (toolCall.functionCalls ?? []).map(async (fc) => {
        this.toolCallsInFlight.add(fc.id);
        const response = await this.runTool(fc);
        this.toolCallsInFlight.delete(fc.id);

        if (this.ended || !this.geminiReady || this.cancelledToolCalls.delete(fc.id)) return;
        this.gemini.sendToolResponse({ functionResponses: [{ id: fc.id, name: fc.name, response }] });
      })
    );

    if (!this.ended && !this.turnHadOutput && this.toolCallsInFlight.size === 0) {
      this.armResponseWatchdog();
    }
  }

  async runTool(fc) {
    if (fc.name !== "search_content_library") {
      this.log("warn", "unknown tool requested", { tool: fc.name });
      return { error: `Unknown tool ${fc.name}.` };
    }

    const query = String(fc.args?.query ?? "").trim().slice(0, 500);
    if (!query) return { results: [], note: "Empty query." };

    try {
      const { results, embedMs, searchMs } = await searchContentLibrary(this.token, query);
      this.log("info", "content search", {
        query,
        results: results.length,
        topScore: results[0]?.score,
        embedMs,
        searchMs,
      });
      this.flushUserText(); // the question comes before the lookup in the saved transcript
      this.history.push({
        role: "tool",
        text: query,
        metadata: { results: results.length, titles: [...new Set(results.map((r) => r.title))] },
        at: new Date().toISOString(),
      });

      if (results.length === 0) {
        return { results: [], note: "Nothing in the Saathealth content library matches this question." };
      }
      // Only what Aira needs to answer; scores and IDs stay out of her context.
      return { results: results.map((r) => ({ title: r.title, topics: r.topics, text: r.text })) };
    } catch (err) {
      this.log("error", "content search failed", { query, error: err.message });
      return { error: "The Saathealth content library is unavailable right now." };
    }
  }

  // ── Silent replies ──────────────────────────────────────────────────────
  //
  // gemini-live-2.5-flash-native-audio intermittently answers user input
  // with nothing: either an empty turn, or no turn at all (mostly when it
  // decides to refuse; seen in guardrail testing). Two defences:
  //   1. an empty turnComplete after real user input -> re-prompt once;
  //   2. a watchdog: no reply audio within RESPONSE_TIMEOUT_MS of the user
  //      finishing -> re-prompt once, then give up and release the client.
  // Only armed after real user input, since silence after noise is correct.

  nudge(reason) {
    if (!this.geminiReady) return;
    this.nudgedThisTurn = true;
    this.log("warn", "no reply to user input, re-prompting gemini", { reason });
    this.gemini.sendRealtimeInput({
      text: "(Your last reply was empty. Reply to my previous message now, following your instructions.)",
    });
    this.armResponseWatchdog();
  }

  armResponseWatchdog() {
    clearTimeout(this.responseWatchdog);
    if (this.toolCallsInFlight.size > 0) return; // re-armed when the lookup returns
    this.responseWatchdog = setTimeout(() => {
      if (this.ended || this.turnHadOutput) return;
      if (!this.nudgedThisTurn) {
        this.nudge("watchdog");
        return;
      }
      this.log("warn", "gemini never replied, releasing client");
      this.send({ type: "error", code: "no_response", message: "Aira didn't catch that. Please try again." });
      this.completeTurn();
    }, RESPONSE_TIMEOUT_MS);
  }

  completeTurn() {
    this.finishTurn({ interrupted: false });
    this.send({ type: "turn_complete" });
  }

  finishTurn({ interrupted }) {
    this.flushUserText();
    this.flushAssistantText({ interrupted });
    this.persist();
    this.turnStartedAt = null;
    this.firstAudioLogged = false;
    this.turnHadOutput = false;
    this.turnUserInput = false;
    this.nudgedThisTurn = false;
    this.awaitingToolAnswer = false;
    clearTimeout(this.responseWatchdog);
  }

  flushUserText() {
    if (this.currentUserText.trim()) {
      this.history.push({
        role: "user",
        text: this.currentUserText.trim(),
        inputMode: this.currentUserMode ?? "voice",
        at: new Date().toISOString(),
      });
    }
    this.currentUserText = "";
    this.currentUserMode = null;
  }

  /** Hands history entries added since the last call to the SessionStore. */
  persist() {
    const fresh = this.history.slice(this.persistedCount);
    this.persistedCount = this.history.length;
    if (fresh.length > 0) this.store.append(fresh);
  }

  flushAssistantText({ interrupted }) {
    if (this.currentAssistantText.trim()) {
      this.history.push({
        role: "assistant",
        text: this.currentAssistantText.trim(),
        interrupted,
        at: new Date().toISOString(),
      });
    }
    this.currentAssistantText = "";
  }

  // ── Client side ─────────────────────────────────────────────────────────

  handleClientMessage(data, isBinary) {
    if (this.ended) return;
    this.resetIdleTimer();

    if (isBinary) {
      this.handleClientAudio(data);
      return;
    }

    let msg;
    try {
      msg = JSON.parse(data.toString());
    } catch {
      this.send({ type: "error", code: "bad_json", message: "Messages must be JSON or binary audio." });
      return;
    }

    switch (msg.type) {
      case "audio_end":
        // Mic turned off. Gemini's VAD only ends the user's turn after it
        // hears silence: if speech stops abruptly (push-to-talk released
        // mid-breath), audioStreamEnd alone often gets no reply at all
        // (reproduced in testing). Pad with silence first.
        this.turnStartedAt = Date.now();
        if (this.geminiReady) {
          this.sendAudioToGemini(TRAILING_SILENCE);
          this.gemini.sendRealtimeInput({ audioStreamEnd: true });
          if (this.turnUserInput && !this.turnHadOutput) this.armResponseWatchdog();
        }
        break;

      case "text": {
        const text = typeof msg.text === "string" ? msg.text.trim() : "";
        if (!text || text.length > MAX_TEXT_LENGTH) {
          this.send({ type: "error", code: "bad_text", message: `Text must be 1-${MAX_TEXT_LENGTH} characters.` });
          return;
        }
        if (!this.geminiReady) {
          this.send({ type: "error", code: "not_ready", message: "Reconnecting, please try again." });
          return;
        }
        this.turnStartedAt = Date.now();
        this.flushUserText(); // any half-spoken words are a separate message
        this.currentUserText = text;
        this.currentUserMode = "text";
        this.turnUserInput = true;
        this.turnHadOutput = false;
        this.armResponseWatchdog();
        // Native-audio models take typed text through the realtime channel;
        // sendClientContent here caused duplicate answers and stray interrupts.
        this.gemini.sendRealtimeInput({ text });
        break;
      }

      case "end":
        this.end(1000, "client ended session");
        break;

      case "ping":
        this.send({ type: "pong" });
        break;

      default:
        this.send({ type: "error", code: "unknown_type", message: `Unknown message type "${msg.type}".` });
    }
  }

  handleClientAudio(buf) {
    // PCM16 mono — an odd byte count means a corrupt frame.
    if (buf.length === 0 || buf.length % 2 !== 0) return;

    if (!this.geminiReady) {
      if (this.pendingAudioBytes + buf.length <= MAX_PENDING_AUDIO_BYTES) {
        this.pendingAudio.push(buf);
        this.pendingAudioBytes += buf.length;
      }
      return;
    }
    this.sendAudioToGemini(buf);
  }

  sendAudioToGemini(buf) {
    this.gemini.sendRealtimeInput({
      audio: { data: buf.toString("base64"), mimeType: `audio/pcm;rate=${config.inputSampleRate}` },
    });
  }

  flushPendingAudio() {
    for (const buf of this.pendingAudio) this.sendAudioToGemini(buf);
    this.pendingAudio = [];
    this.pendingAudioBytes = 0;
  }

  send(obj) {
    if (this.ws.readyState === this.ws.OPEN) this.ws.send(JSON.stringify(obj));
  }

  sendBinary(buf) {
    if (this.ws.readyState === this.ws.OPEN) this.ws.send(buf, { binary: true });
  }

  resetIdleTimer() {
    clearTimeout(this.idleTimer);
    this.idleTimer = setTimeout(
      () => this.end(CloseCode.IDLE_TIMEOUT, "idle timeout"),
      config.idleTimeoutMs
    );
  }

  // ── Teardown ────────────────────────────────────────────────────────────

  /** Returns a promise that settles once the conversation is saved. */
  end(code, reason) {
    if (this.ended) return this.saved ?? Promise.resolve();
    this.ended = true;
    this.finishTurn({ interrupted: true });

    clearTimeout(this.maxDurationTimer);
    clearTimeout(this.idleTimer);
    clearTimeout(this.responseWatchdog);

    try {
      this.gemini?.close();
    } catch {
      // already closed
    }
    if (code && this.ws.readyState === this.ws.OPEN) {
      this.ws.close(code, reason);
    }

    this.log("info", "session ended", {
      reason,
      durationSec: Math.round((Date.now() - this.startedAt) / 1000),
      turns: this.history.filter((h) => h.role === "user").length,
    });
    // finishTurn above handed over the last messages; this closes the session
    // in the API, which then generates its Session History summary.
    this.saved = this.store.end(reason);
    this.onEnd?.(this);
    return this.saved;
  }
}
