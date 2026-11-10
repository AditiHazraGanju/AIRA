# Aira

Aira is Saathealth's voice-first, multilingual AI health assistant (English, Hindi, Marathi and Bengali). This repository is the web front end, built with Next.js.

> **Technical documentation is available here - [Document](./Document)**

Aira works with two other services:

- **saathi-laravel-api**: login (mobile number and OTP), user profile, conversation history and the start pitch.
- **healthtalk-relay**: the WebSocket relay that streams the user's voice and typed messages to Gemini Live and plays Aira's replies.

## Requirements

- Node.js 20 or later
- A running saathi-laravel-api and healthtalk-relay (local or hosted)

## Setup

```bash
npm ci
cp .env.example .env.local   # then replace every YOUR_* placeholder
npm run dev                  # http://localhost:3000
```

Every setting is an environment variable, documented in [`.env.example`](.env.example):

| Variable | What it is |
|---|---|
| `NEXT_PUBLIC_API_BASE_URL` | saathi-laravel-api base URL, ending in `/api/v1` |
| `NEXT_PUBLIC_RELAY_WS_URL` | healthtalk-relay WebSocket URL, ending in `/ws` (`wss://` in production) |
| `NEXT_PUBLIC_TCMLOGGER_*` | Optional remote logging. Off unless the base URL and token are both set. |

`NEXT_PUBLIC_*` values are inlined into the build and visible in the browser, so set them before building and never put a server-side secret in one.

## Scripts

| Command | What it does |
|---|---|
| `npm run dev` | Development server with hot reload |
| `npm run build` | Production build |
| `npm start` | Serve the production build |
| `npm run lint` | ESLint |

## Project layout

- `src/app/`: pages (home and voice chat, login and OTP, registration, profile, session history)
- `src/hooks/useVoiceSession.ts`: microphone capture, the relay connection and audio playback
- `src/locales/`: translations for `eng`, `hin`, `mar` and `ben`
- `src/const/conf.tsx`: reads the environment variables above
- `public/worklets/pcm-capture.js`: audio worklet that captures 16 kHz PCM from the microphone

## Notes

- Microphone capture needs a secure context: `https://`, or `http://localhost` during development.
- The login request sends `role_slug: "ai-chatbot"`. That value is part of the API contract, not this project's name, so leave it unchanged unless the API changes too.
