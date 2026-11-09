import { createHash } from "node:crypto";
import jwt from "jsonwebtoken";
import { config } from "./config.js";

// The relay and saathi-laravel-api run on different servers; allow their
// clocks to differ a little, or a token issued a second "in the future"
// (iat/nbf) is rejected as not yet valid.
const CLOCK_TOLERANCE_SECONDS = 60;

/**
 * A short, non-reversible fingerprint of JWT_SECRET, logged at startup so a
 * mismatch with the API's secret can be spotted without revealing either.
 * Compare with the same fingerprint computed from the API's .env value.
 */
export function jwtSecretFingerprint() {
  return createHash("sha256").update(config.jwtSecret).digest("hex").slice(0, 12);
}

/**
 * Verifies a JWT issued by saathi-laravel-api (tymon/jwt-auth, HS256, signed
 * with the shared JWT_SECRET). Returns { userId } or throws.
 *
 * Known gap: tymon's blacklist (tokens invalidated on logout) lives in
 * Laravel's cache, so a logged-out token stays valid here until it expires.
 */
export function verifyToken(token) {
  if (!token || typeof token !== "string") {
    throw new Error("missing token");
  }
  const claims = jwt.verify(token, config.jwtSecret, {
    algorithms: ["HS256"],
    clockTolerance: CLOCK_TOLERANCE_SECONDS,
  });
  if (!claims.sub) {
    throw new Error("token has no subject");
  }
  return { userId: String(claims.sub) };
}
