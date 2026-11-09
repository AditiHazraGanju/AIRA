// End-to-end check of the relay, run against a local `npm start`.
//
//   npm run test:client                 # English
//   TEST_LANGUAGE=HIN npm run test:client
//
// Scenarios: (1) spoken question from a WAV, (2) typed question,
// (3) barge-in: speak over Aira's reply and expect "interrupted".
//
// Auth: uses TEST_TOKEN if set (copy auth_token from the chatbot's
// localStorage). Otherwise signs a 10-minute token with the relay's own
// JWT_SECRET — local development only.

import "dotenv/config";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import jwt from "jsonwebtoken";
import wav from "wav";
import WebSocket from "ws";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const RELAY_URL = process.env.RELAY_URL || `ws://localhost:${process.env.PORT || 8080}/ws`;
const LANGUAGE = process.env.TEST_LANGUAGE || "ENG";
const INPUT_WAV = path.join(__dirname, "fixtures", "question-en.wav");
const BARGE_IN_WAV = path.join(__dirname, "fixtures", "bargein-en.wav");
const REPLY_WAV = path.join(__dirname, "reply.wav");
const CHUNK_MS = 20;

const token =
  process.env.TEST_TOKEN ||
  jwt.sign({ sub: process.env.TEST_USER_ID || "1" }, process.env.JWT_SECRET, {
    algorithm: "HS256",
    expiresIn: "10m",
  });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function readPcm(file) {
  return new Promise((resolve, reject) => {
    const reader = new wav.Reader();
    const chunks = [];
    reader.on("data", (c) => chunks.push(c));
    reader.on("end", () => resolve(Buffer.concat(chunks)));
    reader.on("error", reject);
    fs.createReadStream(file).pipe(reader);
  });
}

// Streams PCM at realtime pace. Uses wall-clock scheduling because
// setTimeout(20) drifts badly on Windows.
async function streamPcm(ws, pcm, { stopWhen } = {}) {
  const bytesPerChunk = 16000 * 2 * (CHUNK_MS / 1000);
  const start = Date.now();
  for (let i = 0, offset = 0; offset < pcm.length; i++, offset += bytesPerChunk) {
    if (stopWhen?.()) return;
    ws.send(pcm.subarray(offset, offset + bytesPerChunk), { binary: true });
    const wait = start + (i + 1) * CHUNK_MS - Date.now();
    if (wait > 0) await sleep(wait);
  }
}

const ws = new WebSocket(RELAY_URL);
const replyAudio = [];
let transcript = { user: "", assistant: "" };
let waiters = [];
let interruptedSeen = false;
let audioChunksThisTurn = 0;

function waitFor(type, timeoutMs = 30000) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(`timed out waiting for "${type}"`)), timeoutMs);
    waiters.push({ type, resolve: (m) => (clearTimeout(timer), resolve(m)) });
  });
}

ws.on("message", (data, isBinary) => {
  if (isBinary) {
    replyAudio.push(data);
    audioChunksThisTurn += 1;
    return;
  }
  const msg = JSON.parse(data.toString());
  if (msg.type === "transcript") {
    transcript[msg.role] += msg.delta;
  } else if (msg.type === "interrupted") {
    interruptedSeen = true;
    console.log("  <- interrupted (barge-in)");
  } else if (msg.type !== "turn_complete") {
    console.log("  <-", JSON.stringify(msg));
  }
  waiters = waiters.filter((w) => (w.type === msg.type ? (w.resolve(msg), false) : true));
});
ws.on("close", (code, reason) => console.log(`\n[relay closed] ${code} ${reason}`));

function printTurn(label, startedAt, firstAudioAt) {
  console.log(`  heard: "${transcript.user.trim()}"`);
  console.log(`  said:  "${transcript.assistant.trim()}"`);
  if (firstAudioAt) console.log(`  first audio: ${firstAudioAt - startedAt}ms after ${label}`);
  transcript = { user: "", assistant: "" };
  audioChunksThisTurn = 0;
}

async function trackFirstAudio() {
  while (audioChunksThisTurn === 0) await sleep(5);
  return Date.now();
}

async function main() {
  await new Promise((r, j) => (ws.once("open", r), ws.once("error", j)));
  ws.send(JSON.stringify({ type: "start", token, language: LANGUAGE }));
  const ready = await waitFor("ready", 20000);
  console.log(`session ${ready.sessionId} ready (${LANGUAGE})`);

  const pcm = await readPcm(INPUT_WAV);
  const bargeInPcm = await readPcm(BARGE_IN_WAV);

  // 1. Spoken question
  console.log("\n[1] spoken question");
  await streamPcm(ws, pcm);
  ws.send(JSON.stringify({ type: "audio_end" }));
  let t0 = Date.now();
  let firstAudio = trackFirstAudio();
  await waitFor("turn_complete");
  printTurn("audio_end", t0, await firstAudio);

  // 2. Typed question
  console.log("\n[2] typed question");
  ws.send(JSON.stringify({ type: "text", text: "What documents do I need to make a claim?" }));
  t0 = Date.now();
  firstAudio = trackFirstAudio();
  await waitFor("turn_complete");
  printTurn("text sent", t0, await firstAudio);

  // 3. Barge-in: ask something, then talk over the answer once it starts.
  console.log("\n[3] barge-in");
  ws.send(JSON.stringify({ type: "text", text: "Tell me in detail about health insurance in India." }));
  await trackFirstAudio();
  await sleep(1500); // let Aira get a sentence out
  interruptedSeen = false;
  const bargeInStarted = Date.now();
  let interruptedAt = null;
  const watch = setInterval(() => interruptedSeen && !interruptedAt && (interruptedAt = Date.now()), 5);
  await streamPcm(ws, bargeInPcm); // a different question, as a real user would ask
  clearInterval(watch);
  ws.send(JSON.stringify({ type: "audio_end" }));
  console.log(
    `  interrupted received: ${interruptedAt ? `YES, ${interruptedAt - bargeInStarted}ms after user started talking` : "NO"}`
  );
  transcript.assistant = ""; // drop the cut-off reply; show the answer to the new question
  await waitFor("turn_complete", 30000);
  printTurn("barge-in", Date.now());

  // 4. Guardrail: asks about something an admin rule should block. Expect the
  //    admin-configured default message (Admin → HealthTalk AI → Guardrails).
  console.log("\n[4] guardrail");
  transcript = { user: "", assistant: "" };
  ws.send(JSON.stringify({ type: "text", text: "Which medicine should I take for a fever?" }));
  await waitFor("turn_complete");
  printTurn("guardrail question", Date.now());

  const all = Buffer.concat(replyAudio);
  const writer = new wav.FileWriter(REPLY_WAV, { channels: 1, sampleRate: ready.outputSampleRate, bitDepth: 16 });
  writer.end(all);
  console.log(`\nwrote ${REPLY_WAV} (${(all.length / 1024).toFixed(0)} KB)`);

  ws.send(JSON.stringify({ type: "end" }));
  await sleep(300);
  process.exit(0);
}

main().catch((err) => {
  console.error("test failed:", err.message);
  process.exit(1);
});
