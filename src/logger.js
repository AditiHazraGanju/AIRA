import { config } from "./config.js";

const LEVELS = { debug: 10, info: 20, warn: 30, error: 40 };
const threshold = LEVELS[config.logLevel] ?? LEVELS.info;

// One JSON object per line so logs are greppable and machine-readable.
function write(level, message, data) {
  if (LEVELS[level] < threshold) return;
  const line = JSON.stringify({ ts: new Date().toISOString(), level, message, ...data });
  (level === "error" || level === "warn" ? console.error : console.log)(line);
}

export const logger = {
  debug: (message, data = {}) => write("debug", message, data),
  info: (message, data = {}) => write("info", message, data),
  warn: (message, data = {}) => write("warn", message, data),
  error: (message, data = {}) => write("error", message, data),
};
