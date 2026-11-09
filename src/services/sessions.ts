import { apiClient1, apiHandler } from "@/utils/api-handler";
import {
  GET_SESSIONS_API,
  GET_SESSION_API,
  RATE_SESSION_API,
  SESSION_QA_FEEDBACK_API,
} from "@/utils/url-helper";

// Saved conversations with Tara (ht_sessions). The voice relay writes them;
// these calls read them and record the user's ratings and feedback.

export type QaFeedback = "approved" | "rejected" | null;

export interface SessionSummary {
  id: string;
  title: string | null;
  summary: string | null;
  tags: string[];
  language: string;
  status: "active" | "ended";
  summary_status: "pending" | "ready" | "failed" | "skipped";
  turn_count: number;
  rating: number | null;
  started_at: string;
  ended_at: string | null;
}

export interface SessionQaPair {
  id: number;
  question: string;
  answer: string;
  feedback: QaFeedback;
}

export interface SessionMessage {
  role: "user" | "assistant";
  content: string;
  interrupted: boolean;
  at: string;
}

export interface SessionDetail extends SessionSummary {
  qa: SessionQaPair[];
  messages: SessionMessage[];
}

const withId = (template: string, sessionId: string) =>
  template.replace("{session_id}", encodeURIComponent(sessionId));

export async function listSessions(limit = 10): Promise<{ sessions: SessionSummary[]; total: number }> {
  const res = await apiHandler.get<{ sessions: SessionSummary[]; total: number }>(
    apiClient1,
    `${GET_SESSIONS_API}?limit=${limit}`
  );
  if (!res.success) throw new Error(res.message);
  return res.data;
}

export async function getSession(sessionId: string): Promise<SessionDetail> {
  const res = await apiHandler.get<{ session: SessionDetail }>(apiClient1, withId(GET_SESSION_API, sessionId));
  if (!res.success) throw new Error(res.message);
  return res.data.session;
}

export async function rateSession(sessionId: string, rating: number): Promise<void> {
  const res = await apiHandler.post(apiClient1, withId(RATE_SESSION_API, sessionId), { rating });
  if (!res.success) throw new Error(res.message);
}

export async function setQaFeedback(sessionId: string, qaId: number, feedback: QaFeedback): Promise<void> {
  const url = withId(SESSION_QA_FEEDBACK_API, sessionId).replace("{qa_id}", String(qaId));
  const res = await apiHandler.post(apiClient1, url, { feedback });
  if (!res.success) throw new Error(res.message);
}

/** "10 June 2026" and "14:23", matching the Session History design. */
export function formatSessionDate(iso: string | null): { date: string; time: string } {
  if (!iso) return { date: "", time: "" };
  const d = new Date(iso);
  return {
    date: d.toLocaleDateString("en-GB", { day: "numeric", month: "long", year: "numeric" }),
    time: d.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit", hour12: false }),
  };
}
