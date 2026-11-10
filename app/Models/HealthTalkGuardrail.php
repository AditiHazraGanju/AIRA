<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthTalkGuardrail extends Model
{
    protected $table = 'ht_guardrails';
    protected $primaryKey = 'guardrail_id';
    public $timestamps = false;

    protected $fillable = [
        'rule_text',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_deleted', false);
    }
}
