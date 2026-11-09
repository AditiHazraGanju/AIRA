// Runtime configuration. Every value comes from an environment variable; see
// .env.example for what each one is and where to get it.
//
// NEXT_PUBLIC_* values are baked into the build and are visible in the
// browser, so set them before `npm run build` and never put a server-side
// secret in one.

// saathi-laravel-api base URL, including /api/v1.
export const BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || "http://localhost:8000/api/v1";

// HealthTalk voice relay (healthtalk-relay). Must be wss:// in production.
export const RELAY_WS_URL = process.env.NEXT_PUBLIC_RELAY_WS_URL || "ws://localhost:8080/ws";

// Remote logging (TCM Logger). Off unless both the URL and the token are set.
export const TCMLOGGER_BASEURL = process.env.NEXT_PUBLIC_TCMLOGGER_BASE_URL || "";
export const TCMLOGGER_TOKEN = process.env.NEXT_PUBLIC_TCMLOGGER_TOKEN || "";
export const TCMLOGGER_DEBUG = Boolean(TCMLOGGER_BASEURL && TCMLOGGER_TOKEN);
export const TCMLOGGER_CLIENT = process.env.NEXT_PUBLIC_TCMLOGGER_CLIENT || "";
export const TCMLOGGER_DOMAIN = process.env.NEXT_PUBLIC_TCMLOGGER_DOMAIN || "";
export const TCMLOGGER_APP = process.env.NEXT_PUBLIC_TCMLOGGER_APP || "Aira";
