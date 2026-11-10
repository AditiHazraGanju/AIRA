<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Models\HealthTalkContentItem;
use App\Models\HealthTalkContentPurpose;
use App\Services\HealthTalk\ContentIndexer;
use App\Services\HealthTalk\ContentSearch;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContentLibraryController extends Controller
{
    /**
     * Storage disk for uploaded PDFs.
     */
    private const DISK = 'gcs';

    private const UPLOAD_PATH = 'healthtalk/content';

    /**
     * GET /api/v1/healthtalk/content/all
     */
    public function getAllHealthTalkContent()
    {
        try {
            LoggerHelper::logInfo(message: 'Request received', context: __CLASS__, fn: __FUNCTION__);

            $items = HealthTalkContentItem::where('is_deleted', false)
                ->with(['purposes' => function ($q) {
                    $q->with('parent');
                }])
                ->orderByDesc('content_id')
                ->get();

            $contents = $items->map(fn ($item) => $this->formatContent($item));

            LoggerHelper::logInfo(
                message: 'HealthTalk content queried',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['count' => $contents->count()]
            );

            return ResponseService::success(
                $contents->count().' content items retrieved successfully',
                200,
                ['contents' => $contents->values()]
            );
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to retrieve content library', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/healthtalk/content/store
     */
    public function store(Request $request)
    {
        try {
            LoggerHelper::logInfo(
                message: 'Request received',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['request_data' => $request->except('file')]
            );

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content_type' => 'required|in:text,pdf',
                'body' => 'required_if:content_type,text|nullable|string',
                'file' => 'required_if:content_type,pdf|nullable|file|mimes:pdf|max:20480',
                'purposes' => 'required|array|min:1',
                'purposes.*' => 'string|max:150',
                'is_active' => 'nullable|boolean',
                'created_by' => 'nullable|numeric',
            ]);

            $item = DB::transaction(function () use ($validated, $request) {
                $filePath = null;
                $fileName = null;

                if ($validated['content_type'] === 'pdf' && $request->hasFile('file')) {
                    $file = $request->file('file');
                    $fileName = $file->getClientOriginalName();
                    $filePath = $this->storeUpload($file);
                }

                $item = HealthTalkContentItem::create([
                    'title' => $validated['title'],
                    'content_type' => $validated['content_type'],
                    'body' => $validated['content_type'] === 'text' ? ($validated['body'] ?? null) : null,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'index_status' => 'pending',
                    'is_active' => $request->boolean('is_active', true),
                    'is_deleted' => false,
                    'created_on' => Carbon::now(),
                    'created_by' => $validated['created_by'] ?? null,
                ]);

                $purposeIds = $this->resolvePurposeIds($validated['purposes'], $validated['created_by'] ?? null);
                $item->purposes()->sync($purposeIds);

                return $item;
            });

            LoggerHelper::logInfo(
                message: 'HealthTalk content created',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['content_id' => $item->content_id]
            );

            $this->indexAfterResponse($item->content_id);

            return ResponseService::success('Content added successfully', 201, ['content_id' => $item->content_id]);
        } catch (ValidationException $ve) {
            LoggerHelper::logCritical(
                message: 'Validation failed',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $ve, 'errors' => $ve->errors()]
            );

            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to add content', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/healthtalk/content/update/{id}
     * (POST, not PUT — file re-upload needs multipart)
     *
     * A genuine partial update, matching TrainingCategoryMasterController's
     * convention: every field is optional and only applied if present. This
     * matters because the admin's list-view status switch calls this same
     * endpoint with only {is_active, updated_by} — not the full form — to
     * toggle status without reopening the edit modal.
     */
    public function update(Request $request, $id)
    {
        try {
            LoggerHelper::logInfo(
                message: 'Request received',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['request_id' => $id, 'request_data' => $request->except('file')]
            );

            $item = HealthTalkContentItem::find($id);
            if (! $item || $item->is_deleted) {
                return ResponseService::error('Content item not found', 404);
            }

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'content_type' => 'nullable|in:text,pdf',
                'body' => 'nullable|string',
                'file' => 'nullable|file|mimes:pdf|max:20480',
                'purposes' => 'nullable|array|min:1',
                'purposes.*' => 'string|max:150',
                'is_active' => 'nullable|boolean',
                'updated_by' => 'nullable|numeric',
            ]);

            // Anything except the active switch changes what Aira can find.
            $needsReindex = $request->hasFile('file')
                || count(array_intersect(['title', 'content_type', 'body', 'purposes'], array_keys($validated))) > 0;

            DB::transaction(function () use ($validated, $request, $item, $needsReindex) {
                if (array_key_exists('title', $validated)) {
                    $item->title = $validated['title'];
                }

                $contentType = $validated['content_type'] ?? $item->content_type;

                if (array_key_exists('content_type', $validated)) {
                    $item->content_type = $contentType;
                }

                if ($contentType === 'text' && array_key_exists('body', $validated)) {
                    $item->body = $validated['body'];
                }

                if ($contentType === 'pdf' && $request->hasFile('file')) {
                    // Replace the file — remove the old one once the new one is stored.
                    $oldPath = $item->file_path;
                    $file = $request->file('file');
                    $item->file_name = $file->getClientOriginalName();
                    $item->file_path = $this->storeUpload($file);

                    if ($oldPath) {
                        Storage::disk(self::DISK)->delete($oldPath);
                    }
                } elseif (array_key_exists('content_type', $validated) && $contentType === 'text') {
                    // Switched from pdf to text — drop the stale file reference.
                    if ($item->file_path) {
                        Storage::disk(self::DISK)->delete($item->file_path);
                    }
                    $item->file_path = null;
                    $item->file_name = null;
                    $item->body = $validated['body'] ?? $item->body;
                }

                if (array_key_exists('is_active', $validated)) {
                    $item->is_active = $request->boolean('is_active', true);
                }

                if ($needsReindex) {
                    $item->index_status = 'pending';
                    $item->index_error = null;
                }

                $item->modified_on = Carbon::now();
                $item->modified_by = $validated['updated_by'] ?? null;
                $item->save();

                if (array_key_exists('purposes', $validated)) {
                    $purposeIds = $this->resolvePurposeIds($validated['purposes'], $validated['updated_by'] ?? null);
                    $item->purposes()->sync($purposeIds);
                }
            });

            LoggerHelper::logInfo(
                message: 'HealthTalk content updated',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['content_id' => $id]
            );

            if ($needsReindex) {
                $this->indexAfterResponse($item->content_id);
            }

            return ResponseService::success('Content updated successfully', 200);
        } catch (ValidationException $ve) {
            LoggerHelper::logCritical(
                message: 'Validation failed',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $ve, 'errors' => $ve->errors()]
            );

            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to update content', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * PATCH .../content/{id}/toggle-status — admin uses this for the quick
     * list-view switch, distinct from the full update() form.
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'is_active' => 'required|boolean',
                'updated_by' => 'nullable|numeric',
            ]);

            $item = HealthTalkContentItem::find($id);
            if (! $item || $item->is_deleted) {
                return ResponseService::error('Content item not found', 404);
            }

            $item->is_active = $validated['is_active'];
            $item->modified_on = Carbon::now();
            $item->modified_by = $validated['updated_by'] ?? null;
            $item->save();

            return ResponseService::success('Status updated successfully', 200);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to update status', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/v1/healthtalk/content/delete/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'deleted_by' => 'nullable|numeric',
            ]);

            $item = HealthTalkContentItem::find($id);
            if (! $item || $item->is_deleted) {
                return ResponseService::error('Content item not found', 404);
            }

            DB::transaction(function () use ($item, $validated) {
                $item->is_active = false;
                $item->is_deleted = true;
                $item->deleted_on = Carbon::now();
                $item->deleted_by = $validated['deleted_by'] ?? null;
                $item->save();

                // Deleted content must never be searchable; the soft-deleted
                // row stays for audit, its search chunks don't.
                $item->chunks()->delete();
            });

            LoggerHelper::logInfo(
                message: 'HealthTalk content deleted',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['content_id' => $id]
            );

            return ResponseService::success('Content deleted successfully', 200);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to delete content', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/healthtalk/content/search
     * Semantic search over indexed content. Called by the HealthTalk relay
     * when Aira looks something up mid-conversation, so it must stay fast.
     */
    public function search(Request $request, ContentSearch $search)
    {
        try {
            $dimensions = (int) config('services.vertex.embedding_dimensions');
            $validated = $request->validate([
                'query' => 'required_without:embedding|nullable|string|max:500',
                // Or a pre-computed query vector (the relay sends this), with
                // the model that made it so mismatches can't go unnoticed.
                // One closure check instead of `embedding.*` => numeric, which
                // expands to 768 separate rules and is slow on the hot path.
                'embedding' => [
                    "required_without:query", 'nullable', 'array', "size:{$dimensions}",
                    function ($attribute, $value, $fail) {
                        if (! array_is_list($value) || count(array_filter($value, 'is_numeric')) !== count($value)) {
                            $fail('The embedding must be a list of numbers.');
                        }
                    },
                ],
                'embedding_model' => 'required_with:embedding|nullable|string',
                'limit' => 'nullable|integer|min:1|max:8',
            ]);

            $limit = $validated['limit'] ?? 4;
            $started = microtime(true);

            if (! empty($validated['embedding'])) {
                $expected = config('services.vertex.embedding_model');
                if ($validated['embedding_model'] !== $expected) {
                    return ResponseService::error(
                        "Embedding model mismatch: content is indexed with {$expected}",
                        409,
                        ['embedding_model' => ["Expected {$expected}, got {$validated['embedding_model']}"]]
                    );
                }
                $results = $search->searchByVector(array_map('floatval', $validated['embedding']), $limit);
            } else {
                $results = $search->search($validated['query'], $limit);
            }

            LoggerHelper::logInfo(
                message: 'HealthTalk content searched',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'query' => $validated['query'] ?? '(vector)',
                    'results' => count($results),
                    'top_score' => $results[0]['score'] ?? null,
                    'ms' => (int) ((microtime(true) - $started) * 1000),
                ]
            );

            return ResponseService::success(count($results).' results found', 200, ['results' => $results]);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Search failed', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Stores an uploaded PDF on the GCS disk. store() returns false on
     * failure, which used to be saved as file_path "0"; fail loudly instead.
     */
    private function storeUpload(\Illuminate\Http\UploadedFile $file): string
    {
        $path = $file->store(self::UPLOAD_PATH, self::DISK);
        if (! $path) {
            throw new Exception('Could not upload the PDF to storage. Please try again.');
        }

        return $path;
    }

    /**
     * Indexing takes seconds (PDF extraction + embeddings), so it runs after
     * the response has been sent: the admin's save returns immediately and
     * the list shows "pending" until the item is searchable. Failures are
     * recorded on the item; `php artisan healthtalk:index-content` retries.
     */
    private function indexAfterResponse(int $contentId): void
    {
        dispatch(function () use ($contentId) {
            app(ContentIndexer::class)->indexById($contentId);
        })->afterResponse();
    }

    /**
     * Turns the admin's free-typed "Category: Sub-category" tag strings into
     * real ht_content_purposes rows, creating the category and/or
     * sub-category on the fly if they don't already exist. The pivot is
     * attached to the deepest node (the sub-category, if one was given).
     *
     * @param  string[]  $purposeLabels
     * @return int[] purpose_id list to sync onto the content item
     */
    private function resolvePurposeIds(array $purposeLabels, ?int $userId): array
    {
        $ids = [];

        foreach ($purposeLabels as $label) {
            $parts = array_map('trim', explode(':', $label, 2));
            $categoryName = $parts[0];
            $subName = $parts[1] ?? null;

            if ($categoryName === '') {
                continue;
            }

            $category = HealthTalkContentPurpose::where('name', $categoryName)
                ->whereNull('parent_id')
                ->where('is_deleted', false)
                ->first();

            if (! $category) {
                $category = HealthTalkContentPurpose::create([
                    'name' => $categoryName,
                    'parent_id' => null,
                    'is_active' => true,
                    'is_deleted' => false,
                    'created_on' => Carbon::now(),
                    'created_by' => $userId,
                ]);
            }

            if ($subName === null || $subName === '') {
                $ids[] = $category->purpose_id;

                continue;
            }

            $child = HealthTalkContentPurpose::where('name', $subName)
                ->where('parent_id', $category->purpose_id)
                ->where('is_deleted', false)
                ->first();

            if (! $child) {
                $child = HealthTalkContentPurpose::create([
                    'name' => $subName,
                    'parent_id' => $category->purpose_id,
                    'is_active' => true,
                    'is_deleted' => false,
                    'created_on' => Carbon::now(),
                    'created_by' => $userId,
                ]);
            }

            $ids[] = $child->purpose_id;
        }

        return array_values(array_unique($ids));
    }

    private function formatContent(HealthTalkContentItem $item): array
    {
        return [
            'id' => $item->content_id,
            'title' => $item->title,
            'content_type' => $item->content_type,
            'body' => $item->body,
            'file_name' => $item->file_name,
            'file_url' => $item->file_path ? Storage::disk(self::DISK)->url($item->file_path) : null,
            'purposes' => $item->purposes->map(fn ($p) => $p->display_label)->values(),
            'is_active' => (bool) $item->is_active,
            'index_status' => $item->index_status,
            'index_error' => $item->index_error,
            'indexed_at' => optional($item->indexed_on)->toIso8601String(),
            'created_at' => optional($item->created_on)->toIso8601String(),
        ];
    }
}
