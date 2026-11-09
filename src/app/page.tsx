"use client";

import Image from "next/image";
import { useState, useRef, useEffect, useCallback } from "react";
import {
  StartPitchIcon,
  MicrophoneIcon,
  SendIcon,
  // ExclamationIcon, QuestionMarkIcon: used by the hidden "More info" /
  // "Ask another question" buttons below; restore with them.
} from "@/components/ui/Icons";
import toast from "react-hot-toast";
import { useLanguage } from "@/context/LanguageContext";
import { apiClient1, apiHandler } from "@/utils/api-handler";
import { GET_START_PITCH_API } from "@/utils/url-helper";
import { clearUserStorage } from "@/utils/user-storage";
import { useVoiceSession, type VoiceNotice } from "@/hooks/useVoiceSession";
import { ConversationTranscript } from "@/components/chat/ConversationTranscript";
import { EndConversationModal } from "@/components/ui/EndConversationModal";
import { rateSession } from "@/services/sessions";

// ── Types ───────────────────────────────────────────────────────────────────────

interface PitchEntry {
  text: string;
  audio_url: string | null;
}

interface StartPitchData {
  pitches: Record<string, PitchEntry>;
}

// Map UI language labels → API language keys
const LANG_KEY_MAP: Record<string, string> = {
  ENGLISH: "ENG",
  "हिंदी": "HIN",
  "मराठी": "MAR",
  "বাংলা": "BEN",
};

// Voice session notices → translation keys for the toast.
const NOTICE_KEYS: Record<VoiceNotice, string> = {
  connection_failed: "chat_connection_failed",
  mic_denied: "chat_mic_denied",
  no_response: "chat_no_response",
  session_replaced: "chat_session_replaced",
  unauthorized: "chat_session_expired",
};

export default function Home() {
  const [message, setMessage] = useState("");
  const { language, t } = useLanguage();
  const langKey = LANG_KEY_MAP[language] ?? "ENG";

  // Which content the "dynamic content" panel shows: the intro pitch or the
  // live conversation. Whichever the user touched last wins.
  const [panel, setPanel] = useState<"pitch" | "chat">("pitch");

  // ── Voice conversation with Tara (healthtalk-relay) ──
  const voice = useVoiceSession({
    language: langKey,
    onNotice: (notice) => toast.error(t(NOTICE_KEYS[notice])),
    // AppLayout redirects to /login once the stored token is cleared.
    onUnauthorized: clearUserStorage,
  });
  const pauseVoice = voice.pause;

  // ── Start Pitch state ──
  const [pitchData, setPitchData] = useState<Record<string, PitchEntry> | null>(null);
  const [pitchLoading, setPitchLoading] = useState(false);
  const [pitchPlaying, setPitchPlaying] = useState(false);
  const [pitchText, setPitchText] = useState<string | null>(null);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  // Fetch all pitches once on mount
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const response = await apiHandler.get<StartPitchData>(apiClient1, GET_START_PITCH_API);
        if (!cancelled && response.success) {
          setPitchData(response.data.pitches);
        }
      } catch {
        console.error("Failed to fetch start pitch data");
      }
    })();
    return () => { cancelled = true; };
  }, []);

  // Clean up audio on unmount
  useEffect(() => {
    return () => {
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current = null;
      }
    };
  }, []);

  // ── Start Pitch handler ──
  const handleStartPitch = useCallback(() => {
    // If already playing, stop it
    if (pitchPlaying && audioRef.current) {
      audioRef.current.pause();
      audioRef.current.currentTime = 0;
      setPitchPlaying(false);
      return;
    }

    const pitch = pitchData?.[langKey];

    if (!pitch || !pitch.text) {
      return;
    }

    // The pitch and a live conversation can't talk over each other.
    pauseVoice();

    // Show transcript
    setPitchText(pitch.text);
    setPanel("pitch");

    // Play audio
    if (pitch.audio_url) {
      setPitchLoading(true);

      // Stop any previous audio
      if (audioRef.current) {
        audioRef.current.pause();
      }

      const audio = new Audio(pitch.audio_url);
      audioRef.current = audio;

      audio.addEventListener("canplaythrough", () => {
        // Stopped (e.g. the mic was tapped) while the audio was still loading.
        if (audioRef.current !== audio) return;
        setPitchLoading(false);
        setPitchPlaying(true);
        audio.play().catch(() => setPitchPlaying(false));
      }, { once: true });

      audio.addEventListener("ended", () => {
        setPitchPlaying(false);
      }, { once: true });

      audio.addEventListener("error", () => {
        setPitchLoading(false);
        setPitchPlaying(false);
      }, { once: true });

      audio.load();
    }
  }, [pitchData, pitchPlaying, langKey, pauseVoice]);

  const stopPitch = () => {
    if (audioRef.current) {
      audioRef.current.pause();
      audioRef.current = null;
    }
    setPitchPlaying(false);
    setPitchLoading(false);
  };

  const handleMic = () => {
    stopPitch();
    setPanel("chat");
    voice.toggleMic();
  };

  const handleSend = () => {
    if (!message.trim()) return;
    stopPitch();
    setPanel("chat");
    void voice.sendText(message);
    setMessage("");
  };

  // Ending saves the conversation (the relay closes it; the API summarises it
  // for Session History), clears the screen and asks for a star rating.
  const [ratingSessionId, setRatingSessionId] = useState<string | null>(null);

  const handleEndConversation = () => {
    stopPitch();
    const sessionId = voice.finishConversation();
    setPanel("pitch");
    if (sessionId) setRatingSessionId(sessionId);
  };

  const handleRating = async (rating: number) => {
    const sessionId = ratingSessionId;
    if (!sessionId || rating < 1) return;
    try {
      await rateSession(sessionId, rating);
      toast.success(t("chat_rating_thanks"));
    } catch {
      toast.error(t("chat_rating_failed"));
    }
  };

  const statusKey =
    voice.status === "connecting"
      ? "chat_status_connecting"
      : voice.speaking
        ? "chat_status_speaking"
        : voice.awaitingReply
          ? "chat_status_thinking"
          : voice.micActive
            ? "chat_status_listening"
            : "chat_status_idle";
  const statusLive = voice.micActive || voice.speaking;
  const showChat = panel === "chat" && voice.messages.length > 0;

  return (
    <div className="relative h-[calc(100dvh-76px)] md:h-auto md:min-h-[calc(100vh-4rem)] flex flex-col md:flex-row bg-secondary overflow-hidden md:max-w-7xl md:mx-auto">
      {/* Mobile background */}
      <Image
        src="/images/Glass.webp"
        alt=""
        fill
        priority
        className="object-cover md:hidden"
      />

      {/* Left column */}
      <div className="relative z-10 flex flex-col flex-1 min-h-0 md:flex-none md:w-1/2 lg:w-[48%] px-5 md:pl-12 md:pr-6 lg:pr-8 py-6 md:py-12">
        {/* Greeting */}
        <div className="flex shrink-0 items-center gap-4 md:gap-6">
          <div className="relative w-36 h-36 md:w-64 md:h-64 lg:w-72 lg:h-72 shrink-0">
            <Image
              src="/images/Aira.webp"
              alt="Aira"
              fill
              priority
              className="object-contain"
            />
          </div>
          <div>
            <p className="text-primary font-bold text-xl md:text-3xl leading-tight">
              Hello, I&apos;m
            </p>
            <p className="text-primary font-bold text-5xl md:text-6xl leading-tight tracking-tight">
              AIRA!
            </p>
            <p className="text-primary text-sm md:text-lg mt-1 md:mt-2 whitespace-nowrap">
              Your personal AI assistant
            </p>
            <div
              className="mt-3 md:mt-5 inline-flex items-center gap-2 bg-tertiary rounded-full px-4 py-1.5 md:px-5 md:py-4"
              aria-live="polite"
            >
              <span className={`w-4 h-4 rounded-full bg-primary ${statusLive ? "pulse" : "opacity-40"}`} />
              <span className="text-primary text-sm md:text-base">{t(statusKey)}</span>
            </div>
            {voice.messages.length > 0 && (
              <button
                type="button"
                onClick={handleEndConversation}
                className="mt-2 md:mt-3 flex items-center gap-1.5 rounded-full border border-[#F60A0A]/40 text-[#C70808] bg-white/60 px-4 py-1.5 md:px-5 md:py-4 text-sm md:text-base font-medium hover:bg-white transition-colors whitespace-nowrap"
              >
                <span className="w-4 h-4 rounded-sm bg-[#C70808]" aria-hidden />
                {t("chat_end_conversation")}
              </button>
            )}
          </div>
        </div>

        {/* Mobile: live conversation, or the pitch transcript, below the greeting */}
        {showChat ? (
          <ConversationTranscript
            messages={voice.messages}
            className="md:hidden flex-1 min-h-0 overscroll-contain mt-4 mb-3 bg-white/10 backdrop-blur-sm rounded-2xl p-3"
          />
        ) : (
          <>
            {pitchText && (
              // Fills the space between the greeting and the controls, like the conversation does.
              <div className="md:hidden flex-1 min-h-0 overflow-y-auto overscroll-contain mt-4 mb-3 bg-white/10 backdrop-blur-sm rounded-2xl p-4">
                <p className="text-primary text-sm leading-relaxed whitespace-pre-wrap">{pitchText}</p>
              </div>
            )}
          </>
        )}

        <div className={`flex-1 ${showChat || pitchText ? "hidden md:block" : ""}`} />

        {/* Bottom controls */}
        <div className="shrink-0 space-y-3 md:space-y-4">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handleStartPitch}
              disabled={pitchLoading || !pitchData}
              className={`flex-1 flex items-center justify-center gap-2 font-semibold rounded-full py-3.5 md:py-4 btn-hover-lift transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed ${
                pitchPlaying
                  ? "bg-red-500 text-white"
                  : "bg-primary text-white"
              }`}
            >
              {pitchLoading ? (
                <>
                  <svg className="animate-spin w-5 h-5" viewBox="0 0 24 24" fill="none">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  <span className="text-sm md:text-base tracking-wide">LOADING...</span>
                </>
              ) : pitchPlaying ? (
                <>
                  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="6" y="4" width="4" height="16" rx="1" />
                    <rect x="14" y="4" width="4" height="16" rx="1" />
                  </svg>
                  <span className="text-sm md:text-base tracking-wide">STOP PITCH</span>
                </>
              ) : (
                <>
                  <StartPitchIcon />
                  <span className="text-sm md:text-base tracking-wide">START PITCH</span>
                </>
              )}
            </button>

            {/* "More info" and "Ask another question" are hidden until they have a
                defined behaviour. Restore these blocks (and the two icon imports at
                the top) when they're wired up. */}
            {/* Mobile: icon-only buttons */}
            {/* <button
              type="button"
              aria-label="More info"
              className="md:hidden w-12 h-12 shrink-0 flex items-center justify-center bg-[#EBEDFF] rounded-full"
            >
              <ExclamationIcon />
            </button>
            <button
              type="button"
              aria-label="Ask another question"
              className="md:hidden w-12 h-12 shrink-0 flex items-center justify-center bg-[#EBEDFF] rounded-full"
            >
              <QuestionMarkIcon />
            </button> */}
          </div>

          {/* Desktop: full pill buttons */}
          {/* <div className="hidden md:flex items-center gap-3">
            <button
              type="button"
              className="flex-1 flex items-center justify-center gap-2 bg-tertiary text-primary font-medium rounded-full py-3"
            >
              <ExclamationIcon />
              <span>More info</span>
            </button>
            <button
              type="button"
              className="flex-1 flex items-center justify-center gap-2 bg-tertiary text-primary font-medium rounded-full py-3"
            >
              <QuestionMarkIcon />
              <span>Ask another question</span>
            </button>
          </div> */}

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handleMic}
              aria-label={voice.micActive ? "Stop voice input" : "Voice input"}
              aria-pressed={voice.micActive}
              className={`w-12 h-12 md:w-14 md:h-14 shrink-0 flex items-center justify-center bg-[#D3E3AB] border border-[#CBDF9C] rounded-full transition-shadow duration-200 ${
                voice.micActive ? "ring-4 ring-[#CBDF9C] ring-offset-2 ring-offset-secondary animate-pulse" : ""
              }`}
            >
              <MicrophoneIcon />
            </button>
            <div className="flex-1 flex items-center bg-[#F5F6FF]/40 border border-accent rounded-full pl-5 pr-2 py-2">
              <input
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && handleSend()}
                placeholder={t("chat_input_placeholder")}
                maxLength={2000}
                className="flex-1 bg-transparent outline-none text-primary placeholder:text-accent text-sm md:text-base min-w-0"
              />
              <button
                type="button"
                onClick={handleSend}
                aria-label="Send"
                className="w-9 h-9 flex items-center justify-center shrink-0"
              >
                <SendIcon />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Right column (desktop only) — transcript panel */}
      <div className="hidden md:flex md:w-1/2 lg:w-[52%] items-center justify-center px-8 md:pl-4 md:pr-12 lg:pl-6 lg:pr-12 py-12">
        <div className="w-full h-full bg-tertiary rounded-3xl flex items-start justify-center text-center text-primary/60 text-lg px-10 py-10 overflow-y-auto">
          {showChat ? (
            <ConversationTranscript messages={voice.messages} className="w-full h-full max-h-[70vh]" />
          ) : pitchText ? (
            <div className="text-left w-full space-y-4 animate-fade-in">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                  <StartPitchIcon />
                </div>
                <h3 className="text-primary font-semibold text-lg">Aira&apos;s Intro</h3>
                {pitchPlaying && (
                  <div className="flex items-center gap-1 ml-auto">
                    <span className="w-1 h-3 bg-primary rounded-full animate-pulse" />
                    <span className="w-1 h-4 bg-primary rounded-full animate-pulse" style={{ animationDelay: "0.15s" }} />
                    <span className="w-1 h-2 bg-primary rounded-full animate-pulse" style={{ animationDelay: "0.3s" }} />
                    <span className="w-1 h-5 bg-primary rounded-full animate-pulse" style={{ animationDelay: "0.45s" }} />
                  </div>
                )}
              </div>
              <p className="text-primary text-base leading-relaxed whitespace-pre-wrap">{pitchText}</p>
            </div>
          ) : (
            <span className="self-center">For any dynamic content that needs to show up</span>
          )}
        </div>
      </div>

      <EndConversationModal
        isOpen={ratingSessionId !== null}
        onClose={() => setRatingSessionId(null)}
        onSubmit={(rating) => void handleRating(rating)}
      />
    </div>
  );
}
