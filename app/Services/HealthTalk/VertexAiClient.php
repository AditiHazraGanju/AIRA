<?php

namespace App\Services\HealthTalk;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Minimal Vertex AI REST client for the HealthTalk Content Library:
 * text embeddings (indexing + search) and PDF text extraction via Gemini.
 * Authenticates with the same service account key as the GCS disk.
 */
class VertexAiClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    // gemini-embedding-001 takes one input per request on Vertex, so inputs
    // are sent as parallel requests, this many at a time.
    private const EMBED_CONCURRENCY = 6;

    // Gemini writes ~130 tokens/s, so reading a long PDF in one request takes
    // too long (161 pages ≈ 50k tokens ≈ 6 min). Long PDFs are read in page
    // ranges instead, in parallel: 20 pages ≈ 6.5k tokens ≈ 50 s.
    private const PDF_PAGES_PER_REQUEST = 20;

    private const PDF_CONCURRENCY = 10;

    private const PDF_TIMEOUT = 240;

    // ~5x a normal 20-page range. Gemini occasionally gets stuck repeating
    // itself; this cap makes that fail in ~2 min instead of running to 65k.
    private const PDF_MAX_OUTPUT_TOKENS = 32768;

    public const TASK_DOCUMENT = 'RETRIEVAL_DOCUMENT';

    public const TASK_QUERY = 'RETRIEVAL_QUERY';

    /**
     * @param  string[]  $texts
     * @return array<int, float[]> one 768-dim vector per input, same order
     */
    public function embed(array $texts, string $taskType): array
    {
        $url = $this->modelUrl(config('services.vertex.embedding_model')).':predict';
        $dimensions = (int) config('services.vertex.embedding_dimensions');
        $token = $this->accessToken();
        $vectors = [];

        foreach (array_chunk($texts, self::EMBED_CONCURRENCY, preserve_keys: true) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($i) => $pool->as((string) $i)
                    ->withToken($token)
                    ->timeout(30)
                    ->retry(2, 1000, throw: false)
                    ->post($url, [
                        'instances' => [['content' => $batch[$i], 'task_type' => $taskType]],
                        'parameters' => ['outputDimensionality' => $dimensions],
                    ]),
                array_keys($batch)
            ));

            foreach (array_keys($batch) as $i) {
                $response = $responses[(string) $i];
                if ($response instanceof Throwable) {
                    throw new RuntimeException('Embedding request failed: '.$response->getMessage(), 0, $response);
                }
                if ($response->failed()) {
                    $message = $response->json('error.message') ?? $response->body();
                    throw new RuntimeException("Embedding request failed (HTTP {$response->status()}): {$message}");
                }
                $values = $response->json('predictions.0.embeddings.values');
                if (! is_array($values) || count($values) !== $dimensions) {
                    throw new RuntimeException('Embedding response had no '.$dimensions.'-dimension vector');
                }
                $vectors[$i] = $values;
            }
        }

        ksort($vectors);

        return array_values($vectors);
    }

    /**
     * Reads a PDF straight from GCS with Gemini and returns its text. Handles
     * scanned pages and Indic scripts, which PHP PDF parsers often mangle.
     * Pass the page count if known; otherwise Gemini counts the pages first.
     */
    public function extractPdfText(string $gcsUri, ?int $pageCount = null): string
    {
        $pageCount ??= $this->countPdfPages($gcsUri);

        // [first page, last page]; the last range runs "to the end" (null) so
        // an undercounted page total loses nothing.
        $ranges = [];
        for ($first = 1; $first <= max(1, $pageCount); $first += self::PDF_PAGES_PER_REQUEST) {
            $last = $first + self::PDF_PAGES_PER_REQUEST - 1;
            $ranges[] = [$first, $last >= $pageCount ? null : $last];
        }

        $url = $this->modelUrl(config('services.vertex.extraction_model')).':generateContent';
        $token = $this->accessToken();
        $texts = []; // first page => text

        while ($ranges) {
            $batch = array_splice($ranges, 0, self::PDF_CONCURRENCY);
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($i) => $pool->as((string) $i)
                    ->withToken($token)
                    ->timeout(self::PDF_TIMEOUT)
                    ->retry(2, 1000, throw: false)
                    ->post($url, $this->pdfExtractionRequest($gcsUri, ...$batch[$i])),
                array_keys($batch)
            ));

            foreach ($batch as $i => [$first, $last]) {
                $pages = $first === 1 && $last === null ? 'the PDF' : "pages {$first}-".($last ?? 'end');
                $response = $responses[(string) $i];
                if ($response instanceof Throwable) {
                    throw new RuntimeException("Reading {$pages} failed: ".$response->getMessage(), 0, $response);
                }
                if ($response->failed()) {
                    $message = $response->json('error.message') ?? $response->body();
                    throw new RuntimeException("Reading {$pages} failed (HTTP {$response->status()}): {$message}");
                }

                $parts = $response->json('candidates.0.content.parts') ?? [];
                $text = trim(implode('', array_map(fn ($p) => $p['text'] ?? '', $parts)));
                $reason = $response->json('candidates.0.finishReason') ?? 'unknown';

                // Out of tokens: a repetition loop or very dense pages. Read
                // the range again as two halves.
                $end = $last ?? max($pageCount, $first);
                if ($reason === 'MAX_TOKENS' && $end > $first) {
                    $middle = intdiv($first + $end, 2);
                    array_push($ranges, [$first, $middle], [$middle + 1, $last]);

                    continue;
                }
                // STOP with no text is a range past the last page. Anything
                // else (SAFETY, RECITATION...) would leave a gap.
                if ($reason !== 'STOP') {
                    throw new RuntimeException("Reading {$pages} did not finish (finishReason: {$reason})");
                }
                $texts[$first] = $text;
            }
        }

        ksort($texts);
        $text = trim(implode("\n\n", array_filter($texts, fn ($t) => $t !== '')));
        if ($text === '') {
            throw new RuntimeException('PDF text extraction returned no text');
        }

        return $text;
    }

    private function pdfExtractionRequest(string $gcsUri, int $first, ?int $last): array
    {
        $format = 'in its original language, as plain text. '
            .'Keep headings, lists and table rows (one row per line). '
            .'Do not summarise, translate or add anything.';

        // Page wording only for ranges: on a whole document it makes Gemini
        // stop after page 1.
        $prompt = match (true) {
            $first === 1 && $last === null => "Extract all text from this document, {$format} Output only the text.",
            default => 'Extract the text of '.($last === null ? "page {$first} to the last page" : "pages {$first} to {$last}")
                ." of this document (page 1 is the first page of the file), {$format} "
                .'Do not include text from any other page. If the document has no such pages, output nothing. '
                .'Output only the text.',
        };

        return [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['fileData' => ['fileUri' => $gcsUri, 'mimeType' => 'application/pdf']],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0,
                'maxOutputTokens' => self::PDF_MAX_OUTPUT_TOKENS,
                // Transcribing needs no reasoning; thinking only adds time.
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];
    }

    /** Fallback for PDFs whose page tree can't be read without a PDF library. */
    private function countPdfPages(string $gcsUri): int
    {
        $result = $this->generateJson(
            'How many pages does this PDF have?',
            ['type' => 'OBJECT', 'properties' => ['pages' => ['type' => 'INTEGER']], 'required' => ['pages']],
            fileUri: $gcsUri
        );

        return max(1, (int) ($result['pages'] ?? 1));
    }

    /**
     * Single-shot Gemini call constrained to a JSON schema (OpenAPI subset),
     * optionally about a PDF in GCS. Returns the decoded object.
     */
    public function generateJson(string $prompt, array $schema, int $timeout = 60, ?string $fileUri = null): array
    {
        $parts = [['text' => $prompt]];
        if ($fileUri !== null) {
            array_unshift($parts, ['fileData' => ['fileUri' => $fileUri, 'mimeType' => 'application/pdf']]);
        }

        $response = $this->post(
            $this->modelUrl(config('services.vertex.extraction_model')).':generateContent',
            [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                    // Summaries don't need reasoning; skipping it is faster and cheaper.
                    'thinkingConfig' => ['thinkingBudget' => 0],
                ],
            ],
            timeout: $timeout
        );

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $text = implode('', array_map(fn ($p) => $p['text'] ?? '', $parts));
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            $reason = $response['candidates'][0]['finishReason'] ?? 'unknown';
            throw new RuntimeException("Gemini did not return valid JSON (finishReason: {$reason})");
        }

        return $decoded;
    }

    private function modelUrl(string $model): string
    {
        $location = config('services.vertex.location');
        $project = config('services.vertex.project_id');

        return "https://{$location}-aiplatform.googleapis.com/v1/projects/{$project}"
            ."/locations/{$location}/publishers/google/models/{$model}";
    }

    private function post(string $url, array $body, int $timeout): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout($timeout)
            ->retry(2, 1000, throw: false)
            ->post($url, $body);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("Vertex AI request failed (HTTP {$response->status()}): {$message}");
        }

        return $response->json();
    }

    /** OAuth token for the service account, cached until shortly before expiry. */
    private function accessToken(): string
    {
        return Cache::remember('healthtalk.vertex.access_token', now()->addMinutes(50), function () {
            $keyFile = config('services.vertex.key_file');
            if (! $keyFile || ! is_file($keyFile)) {
                throw new RuntimeException('GOOGLE_CLOUD_KEY_FILE is not set or the file does not exist');
            }

            $credentials = new ServiceAccountCredentials(self::SCOPE, json_decode(file_get_contents($keyFile), true));
            $token = $credentials->fetchAuthToken()['access_token'] ?? null;

            if (! $token) {
                throw new RuntimeException('Could not obtain a Google access token');
            }

            return $token;
        });
    }
}
