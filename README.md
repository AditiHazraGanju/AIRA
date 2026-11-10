# AIRA

AIRA is Saathealth's voice-first AI health assistant. Users talk or type to it in English, Hindi, Marathi or Bengali, and it answers from a Content Library that the Saathealth team manages.

This repository holds the four AIRA services. Each one lives on its own branch, with its own codebase, setup and deployment.

---

## Branch index

| Component | Branch | What it does | Stack | Local port |
| :--- | :--- | :--- | :--- | :--- |
| **Frontend** | [`aira-frontend`](../../tree/aira-frontend) | The web app users see: OTP login, registration, voice and text chat with AIRA, conversation history and profile | Next.js 16, React 19 | 3000 |
| **API Service** | [`aira-api`](../../tree/aira-api) | Core backend: login and JWTs, user profiles, saved conversations, Content Library indexing and vector search, guardrails, the introductory message and reports | Laravel 12, PHP 8.4, PostgreSQL + pgvector | 8000 |
| **Admin Portal** | [`aira-admin`](../../tree/aira-admin) | Internal panel to manage the Content Library, guardrails and introductory message, read conversation reports and list users | Laravel 12, PHP 8.4 | 8001 |
| **Relay Server** | [`aira-relay`](../../tree/aira-relay) | WebSocket relay that streams live audio between the browser and Google Gemini Live, applies guardrails, searches the Content Library and saves each conversation | Node.js 20+, WebSockets | 8080 |

## How the services connect

```
Frontend ──HTTPS + JWT──────────▶ API Service ──▶ PostgreSQL (pgvector)
Frontend ──WebSocket + JWT──▶ Relay Server ──▶ API Service
                              Relay Server ──▶ Google Gemini Live
Admin Portal ──X-Admin-Key──▶ API Service
```

- Only the API Service reads and writes the database.
- The Relay Server keeps the Google Cloud credentials off the browser.
- The Admin Portal has no login. Never expose it to the public internet.

---

## Working with individual services

Each branch hosts one independent service, so clone only the one you are working on:

```bash
git clone -b aira-frontend --single-branch https://github.com/AditiHazraGanju/AIRA.git aira-frontend
git clone -b aira-api      --single-branch https://github.com/AditiHazraGanju/AIRA.git aira-api
git clone -b aira-admin    --single-branch https://github.com/AditiHazraGanju/AIRA.git aira-admin
git clone -b aira-relay    --single-branch https://github.com/AditiHazraGanju/AIRA.git aira-relay
```

Each branch has its own `README.md` with setup steps and a `.env.example` that documents every setting. No branch contains real secrets: replace every `YOUR_*` placeholder with your own values.

## Running the full stack locally

Start the services in this order:

1. **PostgreSQL**, with the schema from `aira-api/database/sql/aira_schema.sql` loaded
2. **API Service** on port 8000
3. **Relay Server** on port 8080
4. **Admin Portal** on port 8001
5. **Frontend** on port 3000

These values must be identical across services:

| Value | Shared by |
| :--- | :--- |
| `JWT_SECRET` | API Service and Relay Server |
| `ADMIN_API_KEY` | API Service and Admin Portal |
| Embedding model (`VERTEX_EMBEDDING_MODEL` / `EMBEDDING_MODEL`) | API Service and Relay Server |
