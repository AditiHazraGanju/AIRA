import http from "node:http";
import { WebSocketServer } from "ws";
import { GoogleGenAI } from "@google/genai";
import { config } from "./config.js";
import { logger } from "./logger.js";
import jwt from "jsonwebtoken";
import { verifyToken, jwtSecretFingerprint } from "./auth.js";
import { getGuardrails } from "./laravel.js";
import { LANGUAGES } from "./prompt.js";
import { RelaySession, CloseCode } from "./session.js";

const ai = new GoogleGenAI({
  vertexai: true,
  project: config.gcpProjectId,
  location: config.gcpLocation,
});

const sessions = new Set();
const sessionsByUser = new Map(); // userId -> RelaySession (one live conversation per user)

const server = http.createServer((req, res) => {
  if (req.url === "/healthz") {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: true, sessions: sessions.size }));
    return;
  }
  res.writeHead(404).end();
});

const wss = new WebSocketServer({
  server,
  path: "/ws",
  maxPayload: 256 * 1024,
  verifyClient: ({ origin }) => {
    // No Origin header = not a browser (e.g. test client); the JWT still gates it.
    if (!origin || config.allowedOrigins.length === 0) return true;
    return config.allowedOrigins.includes(origin);
  },
});

/**
 * Protocol: the first frame must be
 *   {"type":"start","token":"<jwt>","language":"ENG|HIN|MAR|BEN"}
 * sent within 5 seconds. The token goes in a message rather than the URL so
 * it doesn't end up in proxy access logs.
 */
wss.on("connection", (ws, req) => {
  const ip = req.headers["x-forwarded-for"]?.split(",")[0].trim() || req.socket.remoteAddress;

  if (sessions.size >= config.maxConcurrentSessions) {
    ws.close(CloseCode.BUSY, "server busy");
    logger.warn("rejected: at capacity", { ip });
    return;
  }

  ws.isAlive = true;
  ws.on("pong", () => (ws.isAlive = true));
  ws.on("error", (err) => logger.warn("client socket error", { ip, error: err.message }));

  const startTimer = setTimeout(() => ws.close(CloseCode.START_TIMEOUT, "start message timeout"), config.startMessageTimeoutMs);

  ws.once("message", async (data, isBinary) => {
    clearTimeout(startTimer);

    let msg;
    try {
      msg = isBinary ? null : JSON.parse(data.toString());
    } catch {
      msg = null;
    }
    if (msg?.type !== "start") {
      ws.close(CloseCode.BAD_REQUEST, "first message must be start");
      return;
    }
    if (!LANGUAGES[msg.language]) {
      ws.close(CloseCode.BAD_REQUEST, "unsupported language");
      return;
    }

    let userId;
    try {
      ({ userId } = verifyToken(msg.token));
    } catch (err) {
      // "invalid signature" = JWT_SECRET differs from the API's;
      // "jwt not active" / "jwt expired" = clock or token age; see README.
      let issuer;
      try {
        issuer = jwt.decode(msg.token)?.iss; // which API issued it (not verified)
      } catch {
        issuer = undefined;
      }
      logger.warn("auth failed", { ip, error: err.message, issuer });
      ws.close(CloseCode.UNAUTHORIZED, "unauthorized");
      return;
    }

    // A second tab or a reconnect replaces the older conversation.
    sessionsByUser.get(userId)?.end(CloseCode.REPLACED, "replaced by a newer session");

    const guardrails = await getGuardrails(msg.token);
    if (ws.readyState !== ws.OPEN) return; // client gave up while we fetched

    const session = new RelaySession({
      ws,
      ai,
      userId,
      token: msg.token,
      language: msg.language,
      guardrails,
      onEnd: (s) => {
        sessions.delete(s);
        if (sessionsByUser.get(s.userId) === s) sessionsByUser.delete(s.userId);
      },
    });
    sessions.add(session);
    sessionsByUser.set(userId, session);

    try {
      await session.start();
    } catch (err) {
      session.log("error", "failed to start gemini session", { error: err.message });
      session.send({ type: "error", code: "upstream_unavailable", message: "The voice service is unavailable." });
      session.end(CloseCode.UPSTREAM_ERROR, "upstream unavailable");
    }
  });
});

// Drop clients whose connection died without a close frame (phones losing signal).
const heartbeat = setInterval(() => {
  for (const ws of wss.clients) {
    if (!ws.isAlive) {
      ws.terminate();
      continue;
    }
    ws.isAlive = false;
    ws.ping();
  }
}, 30000);

async function shutdown(signal) {
  logger.info("shutting down", { signal, sessions: sessions.size });
  clearInterval(heartbeat);
  wss.close();
  server.close();
  // Give in-flight conversations up to 8s to finish saving to the API.
  const saving = [...sessions].map((s) => s.end(1001, "server restarting"));
  await Promise.race([Promise.allSettled(saving), new Promise((r) => setTimeout(r, 8000))]);
  process.exit(0);
}
process.on("SIGINT", () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));

server.listen(config.port, () => {
  logger.info("relay listening", {
    port: config.port,
    path: "/ws",
    model: config.model,
    location: config.gcpLocation,
    laravelApi: config.laravelApiUrl,
    // Must equal the API's: see README "Troubleshooting: 'Your session has expired' right after login".
    jwtSecretFingerprint: jwtSecretFingerprint(),
  });
});
