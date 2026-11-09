"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { getUserName, clearUserStorage } from "@/utils/user-storage";
import { useLanguage } from "@/context/LanguageContext";
import { SessionDetailsModal } from "@/components/ui/SessionDetailsModal";
import { formatSessionDate, listSessions, type SessionSummary } from "@/services/sessions";

const PAGE_SIZE = 10;
const ALL_LIMIT = 100; // the API's maximum

export default function SessionHistoryPage() {
  const router = useRouter();
  const { t } = useLanguage();
  const [userName, setUserNameState] = useState("Guest");
  const [sessions, setSessions] = useState<SessionSummary[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [activeSession, setActiveSession] = useState<SessionSummary | null>(null);

  useEffect(() => {
    window.scrollTo({ top: 0, left: 0, behavior: "instant" });
    const timer = setTimeout(() => {
      window.scrollTo({ top: 0, left: 0, behavior: "instant" });
    }, 50);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    const name = getUserName();
    if (name) setUserNameState(name);
  }, []);

  const fetchSessions = useCallback(async (limit: number) => {
    try {
      const data = await listSessions(limit);
      setSessions(data.sessions);
      setTotal(data.total);
      setFailed(false);
    } catch {
      setFailed(true);
    } finally {
      setLoading(false);
    }
  }, []);

  // "View all" and "Try again": show the loading state, then fetch.
  const load = (limit: number) => {
    setLoading(true);
    setFailed(false);
    void fetchSessions(limit);
  };

  // First page on open (loading starts true, so no state to set up front).
  useEffect(() => {
    let cancelled = false;
    listSessions(PAGE_SIZE)
      .then((data) => {
        if (cancelled) return;
        setSessions(data.sessions);
        setTotal(data.total);
      })
      .catch(() => !cancelled && setFailed(true))
      .finally(() => !cancelled && setLoading(false));
    return () => {
      cancelled = true;
    };
  }, []);

  const handleLogout = () => {
    sessionStorage.setItem("intentional_logout", "true");
    clearUserStorage();
    // AppLayout will handle the redirect to /login
  };

  return (
    <div className="min-h-screen">
      {/* Mobile back button */}
      <div className="md:hidden pt-4 px-6">
        <button
          type="button"
          onClick={() => router.back()}
          aria-label="Back"
          className="w-12 h-12 rounded-xl bg-tertiary flex items-center justify-center"
        >
          <ChevronLeft className="w-6 h-6 text-primary" />
        </button>
      </div>

      <div className="px-6 md:px-8 max-w-7xl mx-auto py-6 md:py-10">
        {/* Name + Logout */}
        <div className="flex items-center justify-between mb-8 md:mb-12">
          <h1 className="text-3xl md:text-5xl font-bold text-primary">{userName}</h1>
          <button
            type="button"
            onClick={handleLogout}
            className="hidden md:block px-8 py-3 bg-[#F60A0A] hover:bg-[#DC2626] text-white font-semibold rounded-2xl transition-colors"
          >
            LOG OUT
          </button>
        </div>

        {/* Session history */}
        <div className="bg-[#F7F8FF] md:bg-transparent rounded-2xl shadow-sm md:shadow-none p-5 md:p-0">
          <div className="flex items-center justify-between mb-4 md:mb-6">
            <h2 className="text-lg md:text-2xl font-bold text-[#021CFC] tracking-wide">
              SESSION HISTORY
            </h2>
            {total > sessions.length && (
              <button
                type="button"
                onClick={() => load(ALL_LIMIT)}
                disabled={loading}
                className="flex items-center gap-1 text-sm md:text-base text-primary hover:underline shrink-0 disabled:opacity-50"
              >
                View all
                <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>

          {loading && sessions.length === 0 ? (
            <p className="py-6 text-primary/60">{t("history_loading")}</p>
          ) : failed ? (
            <div className="py-6 text-primary/70">
              {t("history_load_failed")}{" "}
              <button type="button" onClick={() => load(PAGE_SIZE)} className="underline text-primary">
                {t("history_retry")}
              </button>
            </div>
          ) : sessions.length === 0 ? (
            <p className="py-6 text-primary/60">{t("history_empty")}</p>
          ) : (
            <div className="divide-y-2 divide-[#9AA4FE]">
              {sessions.map((session) => {
                const { date, time } = formatSessionDate(session.started_at);
                return (
                  <button
                    key={session.id}
                    type="button"
                    onClick={() => setActiveSession(session)}
                    className="w-full flex items-center justify-between gap-4 py-4 text-left group"
                  >
                    <div className="min-w-0">
                      <p className="text-lg md:text-xl font-bold text-primary truncate">
                        {session.title || t("history_untitled")}
                      </p>
                      <div className="flex items-center flex-wrap gap-2 mt-1">
                        <span className="text-sm text-primary/50">
                          {date} | {time}
                        </span>
                        {session.tags[0] && (
                          <span className="text-xs md:text-sm bg-tertiary text-[#021CFC] rounded-full px-3 py-1">
                            {session.tags[0]}
                          </span>
                        )}
                      </div>
                    </div>
                    <ChevronRight className="w-7 h-7 text-primary shrink-0 group-hover:translate-x-0.5 transition-transform" />
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {/* Mobile Logout */}
        <button
          type="button"
          onClick={handleLogout}
          className="md:hidden w-full mt-8 py-4 bg-[#F60A0A] hover:bg-[#DC2626] text-white font-semibold text-lg rounded-2xl transition-colors"
        >
          LOG OUT
        </button>
      </div>

      <SessionDetailsModal
        // A new key per session gives the modal fresh state for each one.
        key={activeSession?.id ?? "none"}
        session={activeSession}
        onClose={() => setActiveSession(null)}
        // A summary may have finished while the modal was open; refresh titles.
        onLoaded={(detail) =>
          setSessions((prev) => prev.map((s) => (s.id === detail.id ? { ...s, ...detail } : s)))
        }
      />
    </div>
  );
}
