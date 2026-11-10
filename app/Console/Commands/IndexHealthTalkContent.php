<?php

namespace App\Console\Commands;

use App\Models\HealthTalkContentItem;
use App\Services\HealthTalk\ContentIndexer;
use Illuminate\Console\Command;

/**
 * Indexes Content Library items for Aira's search. Saving an item in the
 * admin panel already indexes it; this is for backfilling existing items,
 * retrying failures, and re-indexing after an embedding model change.
 */
class IndexHealthTalkContent extends Command
{
    protected $signature = 'healthtalk:index-content
        {--id=* : Only these content IDs}
        {--all : Re-index every item, including ones already indexed}';

    protected $description = 'Index HealthTalk Content Library items (pending and failed by default)';

    public function handle(ContentIndexer $indexer): int
    {
        $query = HealthTalkContentItem::where('is_deleted', false)->orderBy('content_id');

        if ($ids = $this->option('id')) {
            $query->whereIn('content_id', $ids);
        } elseif (! $this->option('all')) {
            $query->whereIn('index_status', ['pending', 'failed']);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            $this->info('Nothing to index.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($items as $item) {
            $this->output->write("#{$item->content_id} {$item->title} ... ");
            $ok = $indexer->indexById($item->content_id);
            $fresh = $item->fresh();
            if ($ok) {
                $this->info('indexed ('.$fresh->chunks()->count().' chunks)');
            } else {
                $failed++;
                $this->error($fresh->index_status.($fresh->index_error ? ': '.$fresh->index_error : ''));
            }
        }

        $this->newLine();
        $this->info(($items->count() - $failed)." indexed, {$failed} not indexed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
