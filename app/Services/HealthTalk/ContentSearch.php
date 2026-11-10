<?php

namespace App\Services\HealthTalk;

use App\Models\HealthTalkContentItem;
use Illuminate\Support\Facades\DB;
use Pgvector\Vector;

/**
 * Semantic search over the indexed Content Library (pgvector, cosine).
 * Used by the HealthTalk relay when Aira calls her search tool.
 */
class ContentSearch
{
    // Passages scoring below this are unrelated noise; returning nothing lets
    // Aira say she doesn't know instead of improvising from a weak match.
    // Measured with gemini-embedding-001: relevant 0.59-0.70, unrelated
    // 0.48-0.56. Placeholder content only; re-check once real content is in.
    private const MIN_SCORE = 0.55;

    public function __construct(private VertexAiClient $vertex) {}

    /**
     * @return array<int, array{content_id:int, title:string, topics:string[], text:string, score:float}>
     */
    public function search(string $query, int $limit = 4): array
    {
        return $this->searchByVector($this->vertex->embed([$query], VertexAiClient::TASK_QUERY)[0], $limit);
    }

    /**
     * Search with a query vector embedded by the caller (the relay embeds
     * questions itself to save ~0.7s per lookup). It must come from the same
     * model and task type (RETRIEVAL_QUERY) as config('services.vertex').
     *
     * @param  float[]  $embedding
     */
    public function searchByVector(array $embedding, int $limit = 4): array
    {
        $vector = (string) new Vector($embedding);

        $rows = DB::select(
            'SELECT c.content_id, c.chunk_text, i.title,
                    1 - (c.embedding <=> CAST(? AS vector)) AS score
               FROM ht_content_chunks c
               JOIN ht_content_items i ON i.content_id = c.content_id
              WHERE i.is_active = TRUE
                AND i.is_deleted = FALSE
                AND i.index_status = \'indexed\'
                AND c.embedding IS NOT NULL
              ORDER BY c.embedding <=> CAST(? AS vector)
              LIMIT ?',
            [$vector, $vector, $limit]
        );

        $rows = array_values(array_filter($rows, fn ($r) => (float) $r->score >= self::MIN_SCORE));
        if (empty($rows)) {
            return [];
        }

        $topics = HealthTalkContentItem::with('purposes.parent')
            ->whereIn('content_id', array_unique(array_column($rows, 'content_id')))
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->content_id => $item->purposes->map(fn ($p) => $p->display_label)->values()->all(),
            ]);

        return array_map(fn ($r) => [
            'content_id' => (int) $r->content_id,
            'title' => $r->title,
            'topics' => $topics[$r->content_id] ?? [],
            'text' => $r->chunk_text,
            'score' => round((float) $r->score, 3),
        ], $rows);
    }
}
