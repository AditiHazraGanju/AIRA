import { config } from "./config.js";

// Saves a conversation to saathi-laravel-api (ht_sessions / ht_messages) as
// it happens, using the user's own JWT.
//
// - Turn by turn, so a relay crash loses at most the turn in progress.
// - The session row is only created with the first message, so connections
//   where the user never says anything don't clutter Session History.
// - Requests run one at a time, in order. Failed messages stay queued and go
//   out with the next save; the API ignores re-sent seq numbers.
// - Never throws into the conversation: saving is best-effort.

const MAX_BATCH = 50; // the API's per-request limit
const RETRY_DELAYS_MS = [500, 1500];
const REQUEST_TIMEOUT_MS = 5000;

export class SessionStore {
  constructor({ token, sessionId, language, log }) {
    this.token = token;
    this.sessionId = sessionId;
    this.language = language;
    this.log = log;
    this.nextSeq = 0;
    this.pending = [];
    this.created = false;
    this.chain = Promise.resolve();
  }

  /** @param {{role: string, text: string, inputMode?: string, interrupted?: boolean, metadata?: object, at: string}[]} entries */
  append(entries) {
    for (const e of entries) {
      this.pending.push({
        seq: this.nextSeq++,
        role: e.role,
        content: e.text,
        input_mode: e.inputMode ?? null,
        interrupted: Boolean(e.interrupted),
        metadata: e.metadata ?? null,
        at: e.at,
      });
    }
    return this.enqueue(() => this.flush());
  }

  /** Saves anything left, then marks the session ended (which triggers its summary). */
  end(reason) {
    return this.enqueue(async () => {
      await this.flush();
      if (this.created) {
        await this.request("POST", `/${this.sessionId}/end`, { reason: String(reason ?? "").slice(0, 50) });
      }
    });
  }

  enqueue(task) {
    this.chain = this.chain.then(task).catch((err) => {
      this.log("error", "saving conversation failed", { error: err.message, unsaved: this.pending.length });
    });
    return this.chain;
  }

  async flush() {
    if (this.pending.length === 0) return;
    if (!this.created) {
      await this.request("POST", "", { session_uuid: this.sessionId, language: this.language });
      this.created = true;
    }
    while (this.pending.length > 0) {
      const batch = this.pending.slice(0, MAX_BATCH);
      await this.request("POST", `/${this.sessionId}/messages`, { messages: batch });
      this.pending.splice(0, batch.length);
    }
  }

  async request(method, path, body) {
    let lastError;
    for (let attempt = 0; attempt <= RETRY_DELAYS_MS.length; attempt++) {
      if (attempt > 0) await new Promise((r) => setTimeout(r, RETRY_DELAYS_MS[attempt - 1]));
      try {
        const res = await fetch(`${config.laravelApiUrl}/healthtalk/sessions${path}`, {
          method,
          headers: {
            Authorization: `Bearer ${this.token}`,
            Accept: "application/json",
            "Content-Type": "application/json",
          },
          body: JSON.stringify(body),
          signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
        });
        if (res.ok) return;
        const json = await res.json().catch(() => null);
        lastError = new Error(`${method} sessions${path}: HTTP ${res.status} ${json?.message ?? ""}`.trim());
        // A 4xx other than rate limiting won't succeed on retry.
        if (res.status >= 400 && res.status < 500 && res.status !== 429) break;
      } catch (err) {
        lastError = err;
      }
    }
    throw lastError;
  }
}
