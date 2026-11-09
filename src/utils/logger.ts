/**
 * TCM Logger — TypeScript port of LoggerHelper.php + ApiResponseHelper.php (TCMLogger)
 * Sends structured logs to the DevLogX logging service.
 */

import {
  TCMLOGGER_APP,
  TCMLOGGER_BASEURL,
  TCMLOGGER_CLIENT,
  TCMLOGGER_DEBUG,
  TCMLOGGER_DOMAIN,
  TCMLOGGER_TOKEN,
} from "../const/conf";

// ─── Types ────────────────────────────────────────────────────────────────────

export type LogLevel =
  | "INFO"
  | "ERROR"
  | "WARNING"
  | "DEBUG"
  | "CRITICAL"
  | "ALERT"
  | "EMERGENCY";

export interface TCMLoggerParams {
  log_level?: LogLevel;
  context?: string | null;
  function_name?: string | null;
  message?: string | null;
  user_id?: string | null;
  custom_data?: Record<string, unknown>;
  session_id?: string | null;
  generated_from?: string;
  error_code?: number;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Returns or generates a stable session ID stored in sessionStorage. */
const getSessionId = (): string => {
  if (typeof window === "undefined") return "N/A";
  let id = sessionStorage.getItem("tcm_session_id");
  if (!id) {
    id =
      Math.random().toString(36).substring(2) + Date.now().toString(36);
    sessionStorage.setItem("tcm_session_id", id);
  }
  return id;
};

/** Reads the authenticated user's ID from localStorage (set at login). */
const getStoredUserId = (): string | null => {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("user_id") ?? null;
};

// ─── Core logger (mirrors ApiResponseHelper::TCMLogger) ───────────────────────

/**
 * Sends a single log entry to the TCM Logger API.
 * Fails silently — errors in logging must never break the app.
 */
export const TCMLogger = async ({
  log_level = "INFO",
  context = null,
  function_name = null,
  message = null,
  user_id = null,
  custom_data = {},
  session_id = null,
  generated_from = "SH Consumer App Frontend",
  error_code = 500,
}: TCMLoggerParams = {}): Promise<void> => {
  if (!TCMLOGGER_DEBUG) return;

  try {
    const browser =
      typeof navigator !== "undefined" ? navigator.userAgent : "Unknown";

    const logData: Record<string, unknown> = {
      client_name: TCMLOGGER_CLIENT,
      domain_name: TCMLOGGER_DOMAIN,
      application: TCMLOGGER_APP,
      user_id: user_id ?? getStoredUserId(),
      session_id: session_id ?? getSessionId(),
      browser_info: browser,
      generated_from,
      log_level,
      function_name,
      context,
      message,
      custom_data,
    };

    // ERROR level gets extra fields (mirrors PHP behaviour)
    if (log_level === "ERROR" || log_level === "CRITICAL" || log_level === "EMERGENCY") {
      logData.error_stack = "";
      logData.error_code = error_code;
    }

    const endpoint = log_level === "ERROR" ? "/error" : "/process";
    const url = TCMLOGGER_BASEURL + endpoint;

    await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "User-Agent": "Production Environment",
        "X-Devlog-Token": TCMLOGGER_TOKEN,
      },
      body: JSON.stringify(logData),
      // fire-and-forget friendly
      signal: AbortSignal.timeout(800),
    });
  } catch {
    // Fail silently — logging must never break the app
  }
};

// ─── Convenience wrapper (mirrors LoggerHelper.php) ───────────────────────────

const GENERATED_FROM = "SH Consumer App Frontend";

const Logger = {
  /**
   * Log an informational message.
   * @example Logger.info("User logged in", "AuthContext", "login")
   */
  info: (
    message: string,
    context?: string,
    fn?: string,
    customData?: Record<string, unknown>
  ) =>
    TCMLogger({
      log_level: "INFO",
      message,
      context,
      function_name: fn,
      custom_data: customData,
      generated_from: GENERATED_FROM,
    }),

  /**
   * Log an error message.
   * @example Logger.error("API failed", "api-handler", "handleRequest", {}, 500)
   */
  error: (
    message: string,
    context?: string,
    fn?: string,
    customData?: Record<string, unknown>,
    errorCode?: number
  ) =>
    TCMLogger({
      log_level: "ERROR",
      message,
      context,
      function_name: fn,
      custom_data: customData,
      error_code: errorCode,
      generated_from: GENERATED_FROM,
    }),

  /**
   * Log a warning message.
   */
  warning: (
    message: string,
    context?: string,
    fn?: string,
    customData?: Record<string, unknown>
  ) =>
    TCMLogger({
      log_level: "WARNING",
      message,
      context,
      function_name: fn,
      custom_data: customData,
      generated_from: GENERATED_FROM,
    }),

  /**
   * Log a debug message.
   */
  debug: (
    message: string,
    context?: string,
    fn?: string,
    customData?: Record<string, unknown>
  ) =>
    TCMLogger({
      log_level: "DEBUG",
      message,
      context,
      function_name: fn,
      custom_data: customData,
      generated_from: GENERATED_FROM,
    }),

  /**
   * Log a critical error.
   */
  critical: (
    message: string,
    context?: string,
    fn?: string,
    customData?: Record<string, unknown>,
    errorCode?: number
  ) =>
    TCMLogger({
      log_level: "CRITICAL",
      message,
      context,
      function_name: fn,
      custom_data: customData,
      error_code: errorCode,
      generated_from: GENERATED_FROM,
    }),

  /**
   * Log an alert.
   */
  alert: (
    message: string,
    context?: string,
    fn?: string,
    customData?: Record<string, unknown>
  ) =>
    TCMLogger({
      log_level: "ALERT",
      message,
      context,
      function_name: fn,
      custom_data: customData,
      generated_from: GENERATED_FROM,
    }),

  /**
   * Log an emergency.
   */
  emergency: (
    message: string,
    context?: string,
    fn?: string,
    customData?: Record<string, unknown>,
    errorCode?: number
  ) =>
    TCMLogger({
      log_level: "EMERGENCY",
      message,
      context,
      function_name: fn,
      custom_data: customData,
      error_code: errorCode ?? 500,
      generated_from: GENERATED_FROM,
    }),
};

export default Logger;
