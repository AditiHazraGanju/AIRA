"use client";

import { useEffect, useState } from "react";
import toast from "react-hot-toast";
import { ChevronLeft, ThumbsDown, ThumbsUp } from "lucide-react";
import { useLanguage } from "@/context/LanguageContext";
import {
  formatSessionDate,
  getSession,
  setQaFeedback,
  type QaFeedback,
  type SessionDetail,
  type SessionSummary,
} from "@/services/sessions";

interface SessionDetailsModalProps {
  /** The list row that was tapped; full details are fetched on open. */
  session: SessionSummary | null;
  onClose: () => void;
  onLoaded?: (detail: SessionDetail) => void;
}

// The summary is generated a few seconds after a conversation ends.
const POLL_MS = 3000;
const MAX_POLLS = 10;

export function SessionDetailsModal({ session, onClose, onLoaded }: SessionDetailsModalProps) {
  const { t } = useLanguage();
  const [detail, setDetail] = useState<SessionDetail | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (!session) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [session, onClose]);

  useEffect(() => {
    if (!session) return;
    // State starts empty: the parent remounts this modal (key) per session.
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const fetchDetail = async (poll: number) => {
      try {
        const d = await getSession(session.id);
        if (cancelled) return;
        setDetail(d);
        onLoaded?.(d);
        if (d.summary_status === "pending" && poll < MAX_POLLS) {
          timer = setTimeout(() => void fetchDetail(poll + 1), POLL_MS);
        }
      } catch {
        if (!cancelled) setFailed(true);
      }
    };
    void fetchDetail(0);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
    // onLoaded is a fresh closure each render; refetch only for a new session.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session?.id]);

  if (!session) return null;

  const shown = detail ?? { ...session, qa: [], messages: [] };
  const { date, time } = formatSessionDate(shown.started_at);

  const giveFeedback = async (qaId: number, choice: Exclude<QaFeedback, null>) => {
    const current = detail?.qa.find((q) => q.id === qaId)?.feedback ?? null;
    const next: QaFeedback = current === choice ? null : choice; // tapping again clears it
    const update = (feedback: QaFeedback) =>
      setDetail((d) => d && { ...d, qa: d.qa.map((q) => (q.id === qaId ? { ...q, feedback } : q)) });

    update(next);
    try {
      await setQaFeedback(session.id, qaId, next);
    } catch {
      update(current);
      toast.error(t("history_feedback_failed"));
    }
  };

  return (
    <div
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      className="fixed inset-0 z-[60] bg-black/40 backdrop-blur-sm flex items-center justify-center p-4 md:p-6 overflow-y-auto"
    >
      <div className="relative w-full max-w-2xl max-h-[85vh] bg-white rounded-2xl md:rounded-3xl shadow-2xl overflow-y-auto">
        <div className="px-6 pt-6 pb-10 md:p-10">
          <button
            type="button"
            onClick={onClose}
            aria-label="Back"
            className="w-12 h-12 rounded-xl bg-secondary flex items-center justify-center text-primary hover:bg-tertiary transition-colors"
          >
            <ChevronLeft className="w-6 h-6" />
          </button>

          <h2 className="text-foreground font-bold text-2xl md:text-3xl mt-6">
            {shown.title || t("history_untitled")}
          </h2>
          <div className="flex flex-wrap items-center gap-3 mt-2">
            <span className="text-sm text-foreground/50">
              {date} | {time}
            </span>
            <div className="flex flex-wrap gap-2">
              {shown.tags.map((tag) => (
                <span key={tag} className="text-xs bg-tertiary text-primary rounded-full px-3 py-1">
                  {tag}
                </span>
              ))}
            </div>
          </div>

          {failed ? (
            <p className="mt-6 text-foreground/70">{t("history_load_failed")}</p>
          ) : !detail ? (
            <p className="mt-6 text-foreground/60">{t("history_loading")}</p>
          ) : (
            <>
              <div className="bg-secondary rounded-2xl p-5 md:p-6 mt-6">
                <h3 className="font-bold text-primary text-lg mb-2">Summary</h3>
                <p className="text-foreground/80 leading-relaxed">
                  {detail.summary ||
                    (detail.summary_status === "pending" ? t("history_summary_pending") : t("history_summary_unavailable"))}
                </p>
              </div>

              {detail.qa.length > 0 ? (
                <div className="mt-8 space-y-6">
                  {detail.qa.map((item, i) => (
                    <div key={item.id} className={i < detail.qa.length - 1 ? "pb-6 border-b border-tertiary" : ""}>
                      <h4 className="font-bold text-primary text-lg mb-2">
                        Q{i + 1}: {item.question}
                      </h4>
                      <p className="text-foreground/80 leading-relaxed">{item.answer}</p>
                      <div className="flex items-center gap-3 mt-4">
                        <button
                          type="button"
                          onClick={() => void giveFeedback(item.id, "rejected")}
                          aria-pressed={item.feedback === "rejected"}
                          className={`flex items-center gap-2 bg-[#F7C6BE] text-[#8A2A22] rounded-full px-4 py-2 text-sm font-medium hover:brightness-95 transition ${
                            item.feedback === "rejected" ? "ring-2 ring-[#8A2A22]" : item.feedback ? "opacity-50" : ""
                          }`}
                        >
                          <ThumbsDown className="w-4 h-4" /> edit response
                        </button>
                        <button
                          type="button"
                          onClick={() => void giveFeedback(item.id, "approved")}
                          aria-pressed={item.feedback === "approved"}
                          className={`flex items-center gap-2 bg-[#D7E4B0] text-[#3F5722] rounded-full px-4 py-2 text-sm font-medium hover:brightness-95 transition ${
                            item.feedback === "approved" ? "ring-2 ring-[#3F5722]" : item.feedback ? "opacity-50" : ""
                          }`}
                        >
                          <ThumbsUp className="w-4 h-4" /> approve response
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                // No Q&A yet (summary pending or failed): show the raw conversation.
                <div className="mt-8 space-y-3">
                  {detail.messages.map((m, i) => (
                    <p
                      key={i}
                      className={`leading-relaxed ${m.role === "user" ? "font-bold text-primary" : "text-foreground/80"}`}
                    >
                      {m.role === "user" ? `${t("history_you")}: ` : "Aira: "}
                      {m.content}
                      {m.interrupted && "…"}
                    </p>
                  ))}
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
