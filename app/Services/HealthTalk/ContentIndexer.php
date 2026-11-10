<?php

namespace App\Services\HealthTalk;

use App\Helpers\LoggerHelper;
use App\Models\HealthTalkContentChunk;
use App\Models\HealthTalkContentItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Makes a Content Library item searchable by Aira: extracts its text (PDFs
 * via Gemini), splits it into overlapping chunks, embeds each chunk and
 * replaces the item's rows in ht_content_chunks.
 */
class ContentIndexer
{
    // ~1200 characters is roughly a paragraph or two: small enough to be a
    // precise search hit, big enough to carry a complete answer.
    private const CHUNK_CHARS = 1200;

    private const OVERLAP_CHARS = 200;

    public function __construct(private VertexAiClient $vertex) {}

    public function indexById(int $contentId): bool
    {
        $item = HealthTalkContentItem::with('purposes.parent')->find($contentId);
        if (! $item || $item->is_deleted) {
            return false;
        }

        return $this->index($item);
    }

    /** Returns true when the item ends up indexed. Never throws. */
    public function index(HealthTalkContentItem $item): bool
    {
        // Remember which version we're indexing: if the admin edits the item
        // while this runs, our result is stale and the newer run wins.
        $versionAtStart = optional($item->modified_on)->toIso8601String();
        $item->forceFill(['index_status' => 'processing', 'index_error' => null])->save();

        try {
            $chunks = $this->chunk($this->extractText($item));
            if (empty($chunks)) {
                throw new RuntimeException('No text could be found in this content.');
            }

            // The title and topic go into every embedding so a passage like
            // "submit within 15 days" still matches "Hospicash claim deadline".
            $header = $this->contextHeader($item);
            $vectors = $this->vertex->embed(
                array_map(fn ($c) => $header.$c, $chunks),
                VertexAiClient::TASK_DOCUMENT
            );

            $written = DB::transaction(function () use ($item, $chunks, $vectors, $versionAtStart) {
                $current = HealthTalkContentItem::whereKey($item->content_id)->lockForUpdate()->first();
                if (! $current || $current->is_deleted
                    || optional($current->modified_on)->toIso8601String() !== $versionAtStart) {
                    return false;
                }

                HealthTalkContentChunk::where('content_id', $item->content_id)->delete();
                $now = Carbon::now();
                foreach ($chunks as $i => $text) {
                    HealthTalkContentChunk::create([
                        'content_id' => $item->content_id,
                        'chunk_index' => $i,
                        'chunk_text' => $text,
                        'embedding' => $vectors[$i], // the model's Vector cast converts the float list
                        'created_on' => $now,
                    ]);
                }

                $current->forceFill([
                    'index_status' => 'indexed',
                    'index_error' => null,
                    'indexed_on' => $now,
                ])->save();

                return true;
            });

            LoggerHelper::logInfo(
                message: $written ? 'HealthTalk content indexed' : 'HealthTalk content changed during indexing, result discarded',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['content_id' => $item->content_id, 'chunks' => count($chunks)]
            );

            return $written;
        } catch (Throwable $e) {
            $item->forceFill([
                'index_status' => 'failed',
                'index_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            LoggerHelper::logCritical(
                message: 'HealthTalk content indexing failed',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['content_id' => $item->content_id, 'exception' => $e, 'error_message' => $e->getMessage()]
            );

            return false;
        }
    }

    private function extractText(HealthTalkContentItem $item): string
    {
        if ($item->content_type === 'text') {
            return (string) $item->body;
        }

        $disk = Storage::disk('gcs');
        if (! $item->file_path || ! $disk->exists($item->file_path)) {
            throw new RuntimeException('The PDF file is missing from storage. Please upload it again.');
        }

        $bucket = config('filesystems.disks.gcs.bucket');
        $prefix = trim((string) config('filesystems.disks.gcs.path_prefix'), '/');
        $object = ltrim(($prefix !== '' ? $prefix.'/' : '').$item->file_path, '/');

        return $this->vertex->extractPdfText("gs://{$bucket}/{$object}", $this->pdfPageCount($disk->get($item->file_path)));
    }

    /**
     * The page count from the PDF's page tree, without a PDF library: the
     * root /Type /Pages node has the largest /Count. Null when the tree is
     * inside a compressed object stream; Gemini then counts the pages.
     */
    private function pdfPageCount(?string $pdf): ?int
    {
        if (! $pdf || ! preg_match_all('#<<((?:(?!<<|>>).)*?/Type\s*/Pages\b(?:(?!<<|>>).)*)>>#s', $pdf, $nodes)) {
            return null;
        }

        $counts = [];
        foreach ($nodes[1] as $node) {
            if (preg_match('#/Count\s+(\d+)#', $node, $m)) {
                $counts[] = (int) $m[1];
            }
        }

        return $counts ? max($counts) : null;
    }

    private function contextHeader(HealthTalkContentItem $item): string
    {
        $topics = $item->purposes->map(fn ($p) => $p->display_label)->implode(', ');

        return "Title: {$item->title}\n".($topics !== '' ? "Topic: {$topics}\n" : '')."\n";
    }

    /**
     * Packs paragraphs into ~CHUNK_CHARS chunks, carrying the tail of each
     * chunk into the next so an answer split across a boundary survives.
     *
     * @return string[]
     */
    private function chunk(string $text): array
    {
        $text = trim(preg_replace("/[ \t]+/u", ' ', str_replace("\r\n", "\n", $text)));
        if ($text === '') {
            return [];
        }

        $pieces = [];
        foreach (preg_split("/\n\s*\n/u", $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            array_push($pieces, ...$this->splitLong($paragraph));
        }

        $chunks = [];
        $current = '';
        foreach ($pieces as $piece) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 2 > self::CHUNK_CHARS) {
                $chunks[] = $current;
                $current = $this->overlapTail($current);
            }
            $current = $current === '' ? $piece : $current."\n\n".$piece;
        }
        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** Splits an over-long paragraph at sentence ends (incl. the Devanagari danda), then hard-wraps. */
    private function splitLong(string $paragraph): array
    {
        if (mb_strlen($paragraph) <= self::CHUNK_CHARS) {
            return [$paragraph];
        }

        $parts = [];
        $current = '';
        foreach (preg_split('/(?<=[.!?।])\s+/u', $paragraph) as $sentence) {
            while (mb_strlen($sentence) > self::CHUNK_CHARS) {
                $parts[] = mb_substr($sentence, 0, self::CHUNK_CHARS);
                $sentence = mb_substr($sentence, self::CHUNK_CHARS);
            }
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > self::CHUNK_CHARS) {
                $parts[] = $current;
                $current = '';
            }
            $current = $current === '' ? $sentence : $current.' '.$sentence;
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /** The last ~OVERLAP_CHARS of a chunk, starting at a word boundary. */
    private function overlapTail(string $chunk): string
    {
        if (mb_strlen($chunk) <= self::OVERLAP_CHARS) {
            return $chunk;
        }
        $tail = mb_substr($chunk, -self::OVERLAP_CHARS);
        $space = mb_strpos($tail, ' ');

        return $space === false ? $tail : mb_substr($tail, $space + 1);
    }
}
