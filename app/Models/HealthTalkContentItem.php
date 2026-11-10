<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HealthTalkContentItem extends Model
{
    protected $table = 'ht_content_items';
    protected $primaryKey = 'content_id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'content_type',
        'body',
        'file_path',
        'file_name',
        'index_status',
        'index_error',
        'indexed_on',
        'created_on',
        'created_by',
        'modified_on',
        'modified_by',
        'is_deleted',
        'deleted_on',
        'deleted_by',
        'is_active',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_active' => 'boolean',
        'created_on' => 'datetime',
        'modified_on' => 'datetime',
        'deleted_on' => 'datetime',
        'indexed_on' => 'datetime',
    ];

    public function purposes(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthTalkContentPurpose::class,
            'ht_content_item_purpose',
            'content_id',
            'purpose_id'
        );
    }

    public function chunks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HealthTalkContentChunk::class, 'content_id', 'content_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_deleted', false);
    }
}
