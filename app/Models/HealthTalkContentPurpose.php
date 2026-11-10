<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HealthTalkContentPurpose extends Model
{
    protected $table = 'ht_content_purposes';
    protected $primaryKey = 'purpose_id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'parent_id',
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
    ];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'purpose_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'purpose_id');
    }

    public function contentItems(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthTalkContentItem::class,
            'ht_content_item_purpose',
            'purpose_id',
            'content_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_deleted', false);
    }

    /**
     * "Category: Sub-category" as shown in the admin's Purpose tag field.
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->parent_id && $this->relationLoaded('parent') && $this->parent) {
            return $this->parent->name.': '.$this->name;
        }

        return $this->name;
    }
}
