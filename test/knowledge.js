// Content Library grounding check, one conversation, paced like a real user.
// Needs Hospicash content in the library (see README "Testing grounding").
//
//   node test/knowledge.js
//   TEST_LANGUAGE=HIN node test/knowledge.js

import "dotenv/config";
import jwt from "jsonwebtoken";
import WebSocket from "ws";

const RELAY_URL = process.env.RELAY_URL || `ws://localhost:${process.env.PORT || 8080}/ws`;
const LANGUAGE = process.env.TEST_LANGUAGE || "ENG";
const QUESTIONS = [
  "Hi Aira!",
  "How much does Hospicash pay per day?",
  "And how many days do I have to submit a claim?", // follow-up: no product name
  "Is dental treatment covered?",
  "What is the premium for the Saathealth critical illness plan?", // not in the library
];

const token =
  process.env.TEST_TOKEN ||
  jwt.sign({ sub: process.env.TEST_USER_ID || "1" }, process.env.JWT_SECRET, { algorithm: "HS256", expiresIn: "10m" });

const ws = new WebSocket(RELAY_URL);
let said = "";
let audioBytes = 0;
let firstAudioAt = null;
let onTurn = null;

ws.on("message", (data, isBinary) => {
  if (isBinary) {
    audioBytes += data.length;
    firstAudioAt ??= Date.now();
    return;
  }
  const msg = JSON.parse(data.toString());
  if (msg.type === "transcript" && msg.role === "assistant") said += msg.delta;
  if ((msg.type === "turn_complete" || msg.type === "ready") && onTurn) onTurn();
  if (msg.type === "error") console.log("  <- error", msg.code);
});

const next = (timeoutMs = 30000) =>
  new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error("timed out")), timeoutMs);
    onTurn = () => (clearTimeout(t), resolve());
  });

await new Promise((r) => ws.once("open", r));
ws.send(JSON.stringify({ type: "start", token, language: LANGUAGE }));
await next(20000);
console.log(`session ready (${LANGUAGE})\n`);

for (const q of QUESTIONS) {
  said = "";
  audioBytes = 0;
  firstAudioAt = null;
  const sentAt = Date.now();
  ws.send(JSON.stringify({ type: "text", text: q }));
  await next();
  const audioSec = audioBytes / (24000 * 2);
  console.log(`Q: ${q}`);
  console.log(`A: (${firstAudioAt ? firstAudioAt - sentAt : "-"}ms to first audio) "${said.trim()}"\n`);
  // Wait for Aira's audio to finish "playing" before the next question.
  if (firstAudioAt) {
    const remaining = firstAudioAt + audioSec * 1000 - Date.now();
    if (remaining > 0) await new Promise((r) => setTimeout(r, remaining + 300));
  }
}

ws.send(JSON.stringify({ type: "end" }));
setTimeout(() => process.exit(0), 300);
