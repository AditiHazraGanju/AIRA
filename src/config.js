import "dotenv/config";

function required(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required environment variable ${name} (see .env.example)`);
  }
  return value;
}

function int(name, fallback) {
  const raw = process.env[name];
  if (raw === undefined || raw === "") return fallback;
  const n = Number.parseInt(raw, 10);
  if (Number.isNaN(n)) throw new Error(`${name} must be an integer, got "${raw}"`);
  return n;
}

export const config = {
  port: int("PORT", 8080),
  allowedOrigins: (process.env.ALLOWED_ORIGINS || "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean),
  logLevel: process.env.LOG_LEVEL || "info",

  jwtSecret: required("JWT_SECRET"),

  laravelApiUrl: required("LARAVEL_API_URL").replace(/\/+$/, ""),
  guardrailCacheMs: int("GUARDRAIL_CACHE_SECONDS", 60) * 1000,

  gcpProjectId: required("GCP_PROJECT_ID"),
  gcpLocation: process.env.GCP_LOCATION || "us-central1",
  model: process.env.GEMINI_LIVE_MODEL || "gemini-live-2.5-flash-native-audio",
  voice: process.env.GEMINI_VOICE || null,

  // Content Library search. The model must match VERTEX_EMBEDDING_MODEL in
  // saathi-laravel-api (it rejects vectors from any other model).
  embeddingModel: process.env.EMBEDDING_MODEL || "gemini-embedding-001",
  embeddingLocation: process.env.EMBEDDING_LOCATION || "asia-south1",
  embeddingDimensions: 768,
  searchTimeoutMs: int("SEARCH_TIMEOUT_MS", 4000),

  maxConcurrentSessions: int("MAX_CONCURRENT_SESSIONS", 50),
  maxSessionMs: int("MAX_SESSION_MINUTES", 15) * 60 * 1000,
  idleTimeoutMs: int("IDLE_TIMEOUT_SECONDS", 120) * 1000,

  // Fixed by the client contract, not configurable.
  inputSampleRate: 16000,
  outputSampleRate: 24000,
  startMessageTimeoutMs: 5000,
  maxReconnectAttempts: 3,
};
