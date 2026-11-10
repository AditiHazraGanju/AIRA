<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/**
 * Populated by the future chunking/embedding job (P1-07) — not written
 * to by the Content Library CRUD in this pass. Model exists now so that
 * work has a home without another schema change.
 *
 * embedding is a native pgvector(768) column (see healthtalk_schema.sql
 * for the dimension note). The Vector cast round-trips it as a
 * Pgvector\Laravel\Vector object (->toArray() for the raw floats).
 * HasNeighbors adds ::nearestNeighbors($vector, Distance::Cosine) for
 * P1-08's retrieval query — nothing calls it yet.
 */
class HealthTalkContentChunk extends Model
{
    use HasNeighbors;

    protected $table = 'ht_content_chunks';
    protected $primaryKey = 'chunk_id';
    public $timestamps = false;

    protected $fillable = [
        'content_id',
        'chunk_index',
        'chunk_text',
        'embedding',
        'created_on',
    ];

    protected $casts = [
        'embedding' => Vector::class,
        'created_on' => 'datetime',
    ];

    public function contentItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HealthTalkContentItem::class, 'content_id', 'content_id');
    }
}
