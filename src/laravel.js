import { config } from "./config.js";
import { logger } from "./logger.js";

// Guardrails are global (the same for every user), so one cached copy serves
// all sessions. Fetched with the connecting user's own JWT, since the
// guardrail endpoints only require a valid token.
let cache = null; // { rules: string[], defaultMessages: Record<string,string>, fetchedAt: number }

async function getJson(path, token) {
  const res = await fetch(`${config.laravelApiUrl}${path}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    signal: AbortSignal.timeout(5000),
  });
  const body = await res.json().catch(() => null);
  if (!res.ok || !body?.success) {
    throw new Error(`GET ${path} failed: HTTP ${res.status} ${body?.message ?? ""}`.trim());
  }
  return body.data;
}

/**
 * Returns { rules, defaultMessages }. On fetch failure, falls back to the last
 * good copy; if there has never been one, returns empty guardrails so the
 * session still starts with the built-in base rules from prompt.js.
 */
export async function getGuardrails(token) {
  if (cache && Date.now() - cache.fetchedAt < config.guardrailCacheMs) {
    return cache;
  }

  try {
    const [list, settings] = await Promise.all([
      getJson("/healthtalk/guardrails/all", token),
      getJson("/healthtalk/guardrails/settings", token),
    ]);

    cache = {
      rules: (list?.guardrails ?? [])
        .filter((g) => g.is_active && g.rule_text?.trim())
        .map((g) => g.rule_text.trim()),
      defaultMessages: settings?.default_messages ?? {},
      fetchedAt: Date.now(),
    };
    logger.info("guardrails refreshed", { rules: cache.rules.length });
    return cache;
  } catch (err) {
    if (cache) {
      logger.warn("guardrail refresh failed, using stale copy", { error: err.message });
      return cache;
    }
    logger.error("guardrail fetch failed and no cached copy — starting with base rules only", {
      error: err.message,
    });
    return { rules: [], defaultMessages: {}, fetchedAt: 0 };
  }
}
