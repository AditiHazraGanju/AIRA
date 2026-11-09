# healthtalk-relay

WebSocket relay between the Aira web front end and the Gemini Live API on Vertex AI.

```
browser mic ──PCM16 16kHz──▶ relay ──▶ Gemini Live (us-central1)
browser speaker ◀──PCM16 24kHz── relay ◀──
```

The relay exists so that the GCP service account never reaches the browser. It also:

- verifies the chatbot JWT
- builds Aira's system prompt (language lock, guardrails from Laravel)
- forwards audio and transcripts
- signals barge-in
- resumes the Gemini session when Google rotates the connection

## Run locally

```bash
cp .env.example .env      # replace every YOUR_* placeholder (each variable is documented in the file)
npm install
npm start                 # ws://localhost:8080/ws, health check at /healthz
npm run test:client       # end-to-end: spoken turn, typed turn, barge-in, guardrail
TEST_LANGUAGE=HIN npm run test:client
node test/guardrail.js    # blocked vs allowed questions (uses the admin panel's live guardrails)
node test/knowledge.js    # Content Library answers, incl. a follow-up (needs Hospicash content indexed)
```

The test scripts sign a short-lived token with `JWT_SECRET` (local development only). To use a real chatbot login instead, set `TEST_TOKEN`.

## Client protocol

The connection is `ws(s)://<host>/ws`. Text frames carry JSON. Binary frames carry raw PCM16 little-endian mono audio.

### Client → relay

| Frame | When |
|---|---|
| `{"type":"start","token":"<jwt>","language":"ENG"\|"HIN"\|"MAR"\|"BEN"}` | **First frame, within 5 s.** Wait for `ready` before sending audio. |
| binary: PCM16, **16 kHz** mono | Mic audio, ideally 20–40 ms per frame. |
| `{"type":"audio_end"}` | Mic turned off (push-to-talk released). |
| `{"type":"text","text":"..."}` | Typed message (max 2000 chars). |
| `{"type":"end"}` | User ended the conversation. |

To change the language, close the socket and open a new session. Gemini fixes the system prompt for the whole session.

### Relay → client

| Frame | Meaning |
|---|---|
| `{"type":"ready","sessionId","language","inputSampleRate":16000,"outputSampleRate":24000}` | Session is live. |
| binary: PCM16, **24 kHz** mono | Aira's voice. Queue the frames and play them in order. |
| `{"type":"transcript","role":"user"\|"assistant","delta":"..."}` | Incremental transcript text. Append it to the current bubble. |
| `{"type":"interrupted"}` | The user spoke over Aira, or sent text while she was talking. **Stop playback, discard queued audio and close Aira's current bubble.** No separate `turn_complete` follows for the cut-off reply. |
| `{"type":"turn_complete"}` | Aira finished the turn. Close the current bubbles. |
| `{"type":"error","code":"no_response",...}` | Gemini didn't reply even after a retry. Show "please try again"; a `turn_complete` follows. |
| `{"type":"error","code","message"}` | Non-fatal unless followed by a close. |

Typed text and `interrupted` can arrive at any time. The client should always treat `interrupted` as "stop audio now".

### Close codes

| Code | Reason |
|---|---|
| 4000 | Bad first message or unsupported language |
| 4001 | No `start` within 5 s |
| 4003 | Invalid or expired token: re-login |
| 4008 | Idle for `IDLE_TIMEOUT_SECONDS` |
| 4009 | Replaced by a newer session for the same user |
| 4010 | Hit `MAX_SESSION_MINUTES` |
| 1011 | Gemini unavailable or disconnected (after resume attempts) |
| 1013 | Relay at `MAX_CONCURRENT_SESSIONS` |

## Content Library search

Aira answers from the admin's Content Library: Saathealth's products and policies as well as health education. She has one tool, `search_content_library`, which she calls for every question except greetings, small talk and clearly restricted topics, because she can't know what the library holds without searching. Here's what happens:

1. Gemini decides to search and writes a self-contained query. For a follow-up, that includes the product name from earlier in the conversation.
2. The relay embeds the query with `gemini-embedding-001` in `asia-south1` (768 dimensions, task type `RETRIEVAL_QUERY`).
3. The relay sends the vector to `POST /api/v1/healthtalk/content/search` on saathi-laravel-api, using the user's own JWT. Laravel runs the pgvector search over `ht_content_chunks` and returns up to 4 passages scoring ≥ 0.55.
4. Aira answers from those passages. If there are none, or the search fails, she says she doesn't have that information.

`EMBEDDING_MODEL` must match `VERTEX_EMBEDDING_MODEL` in saathi-laravel-api. Laravel rejects vectors from any other model with a 409.

**Indexing** happens in saathi-laravel-api (`App\Services\HealthTalk\ContentIndexer`) after every save in the admin panel:

1. PDFs are read straight from GCS by Gemini, so scanned pages and Indic scripts work.
2. The text is split into ~1200-character overlapping chunks.
3. Each chunk is embedded and stored.

The admin list shows the status of each item (Ready / Indexing… / Failed). To backfill or retry: `php artisan healthtalk:index-content` (pending and failed items) or `--all`.

**Latency.** A grounded answer starts ~2.9–3.5 s after the question locally, against ~1 s for small talk. The time breaks down as:

- ~0.65 s for Gemini to decide to search
- ~0.25–0.8 s to embed the query
- ~0.8 s for the Laravel search under `artisan serve`, of which ~0.5 s is the dev server itself
- ~1 s for Gemini to generate the answer

## Saved conversations

Every conversation is saved to saathi-laravel-api for the chatbot's Session History (`src/persistence.js`). The `sessionId` in the `ready` message is also the saved conversation's id, which the chatbot uses for the rating.

- **What is saved:** after each turn, the relay posts that turn's messages to `/api/v1/healthtalk/sessions/{id}/messages` using the user's JWT. That covers the user's words (voice or typed), Aira's reply (flagged if cut off), and any Content Library lookup.
- **When the session row appears:** only with the first message, so connections where nobody spoke leave no trace.
- **Failures:** failed saves are retried and kept for the next turn. The API ignores re-sent messages, and a save failure never interrupts the conversation.
- **At the end:** when the session ends, the relay calls `/end` and the API generates the title, summary, tags and Q&A pairs with Gemini. On shutdown, the relay waits up to 8 s for in-flight saves.
- **Crashes:** if the relay dies mid-conversation, the API marks the session ended 20 minutes after its last activity, the next time the user opens Session History.

## Troubleshooting: "Your session has expired" right after login

The relay closes the socket with code **4003** when it rejects the user's token. Look for `"message":"auth failed"` in the relay log. Its `error` field gives the cause:

| `error` | Cause | Fix |
|---|---|---|
| `invalid signature` | The relay's `JWT_SECRET` is not the same as the API's | Copy the exact `JWT_SECRET` from that environment's saathi-laravel-api `.env`, then restart the relay |
| `jwt not active` | The API server's clock is more than 60 s ahead of the relay's | Enable NTP time sync on both servers |
| `jwt expired` | The token really has expired | Nothing to fix; the user logs in again |

To compare secrets without revealing them, compare the fingerprints. They must be identical:

- **Relay:** printed at startup, as `jwtSecretFingerprint` in the `relay listening` log line.
- **API:** `php artisan tinker --execute="echo substr(hash('sha256', config('jwt.secret')), 0, 12);"`

The log line's `issuer` shows which API issued the token, which helps spot a relay pointed at the wrong environment. Since the chatbot checks the token with the API before logging anyone out, a relay misconfiguration shows "Couldn't connect to Aira" instead.

## Known limitations

- **Region.** No Live model is served from `asia-south1`, so conversation audio is processed in `us-central1`. See `live-api-spike` for the measurements.
- **No VAD tuning.** Setting `automaticActivityDetection.silenceDurationMs` makes this model never reply, so the default VAD is used.
- **VAD needs trailing silence.** If speech stops abruptly, Gemini often never answers, and `audioStreamEnd` alone doesn't fix it. The relay therefore sends 1 s of silence on `audio_end`. In open-mic mode the mic's own silence does the same job.
- **Silent replies.** The model occasionally answers with nothing, mostly when declining a guardrail topic. The relay re-prompts once: immediately on an empty turn, or after 8 s with no reply audio (the watchdog). If the retry is also silent, the client gets `no_response`.
- **Guardrail wording.** Aira says the admin's default message word for word only when one exists for the session language. Otherwise she declines in her own words. Placeholder admin text such as "Test" is often paraphrased; real sentences are followed reliably. A guardrail can be a topic ("Don't talk about medicine", declined without searching) or an instruction ("only answer from the approved database", followed by searching first).
- **Searching isn't guaranteed.** Gemini decides when to call the search tool. Right after declining an off-topic question it occasionally declines the next valid question too (3 of 23 health questions in testing, all straight after a refusal).
- **Logged-out tokens.** The relay verifies JWTs locally, so a token that was logged out stays valid here until it expires. Laravel's blacklist isn't visible to the relay.
- **No grounding yet.** Answers don't use the Content Library, so Aira may describe products generically.
- **Transcripts aren't stored yet.** They're kept in memory per session. Persisting them is waiting on the `ht_sessions` / `ht_messages` tables.

## Deployment

The relay needs a **long-running Node.js process (≥ 20) that can hold WebSocket connections open**. Shared/static hosting won't work; a VPS, a container platform or Cloud Run will. Put it behind TLS (`wss://`) and set `ALLOWED_ORIGINS` to the chatbot's domain.
