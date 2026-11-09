import { GoogleGenAI } from "@google/genai";
import { config } from "./config.js";

// Content Library search for Aira's search_content_library tool.
//
// The relay embeds the question itself (a warm connection from this
// long-lived process is ~0.7s faster than embedding inside Laravel), then
// Laravel runs the pgvector search, so the database stays owned by the API.

const embedder = new GoogleGenAI({
  vertexai: true,
  project: config.gcpProjectId,
  location: config.embeddingLocation,
});

export const SEARCH_TOOL = {
  functionDeclarations: [
    {
      name: "search_content_library",
      description:
        "Search Saathealth's approved content library: health education (such as women's health, " +
        "pregnancy and nutrition) and products such as Hospicash, coverage, premiums, claims, " +
        "documents and policies. Returns relevant passages.",
      parameters: {
        type: "OBJECT",
        properties: {
          query: {
            type: "STRING",
            description:
              "A short, self-contained search query in English for the user's latest question, " +
              "including the product or topic from earlier in the conversation if the user " +
              "didn't repeat it.",
          },
        },
        required: ["query"],
      },
    },
  ],
};

function withTimeout(promise, ms, label) {
  let timer;
  return Promise.race([
    promise,
    new Promise((_, reject) => {
      timer = setTimeout(() => reject(new Error(`${label} timed out after ${ms}ms`)), ms);
    }),
  ]).finally(() => clearTimeout(timer));
}

/**
 * @returns {Promise<{results: {title: string, topics: string[], text: string, score: number}[], embedMs: number, searchMs: number}>}
 */
export async function searchContentLibrary(token, query) {
  const started = Date.now();

  const embedded = await withTimeout(
    embedder.models.embedContent({
      model: config.embeddingModel,
      contents: [query],
      config: { taskType: "RETRIEVAL_QUERY", outputDimensionality: config.embeddingDimensions },
    }),
    config.searchTimeoutMs,
    "embedding"
  );
  const embedding = embedded.embeddings?.[0]?.values;
  if (!embedding?.length) throw new Error("embedding returned no vector");
  const embedMs = Date.now() - started;

  const remaining = Math.max(500, config.searchTimeoutMs - embedMs);
  const res = await fetch(`${config.laravelApiUrl}/healthtalk/content/search`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ embedding, embedding_model: config.embeddingModel, limit: 4 }),
    signal: AbortSignal.timeout(remaining),
  });
  const body = await res.json().catch(() => null);
  if (!res.ok || !body?.success) {
    throw new Error(`search failed: HTTP ${res.status} ${body?.message ?? ""}`.trim());
  }

  return { results: body.data.results ?? [], embedMs, searchMs: Date.now() - started - embedMs };
}
