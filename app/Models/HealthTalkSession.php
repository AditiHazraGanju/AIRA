<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One conversation with Aira. Created and appended to by the HealthTalk
 * relay; summarised by App\Services\HealthTalk\SessionSummarizer on end.
 */
class HealthTalkSession extends Model
{
    protected $table = 'ht_sessions';
    protected $primaryKey = 'session_id';
    public $timestamps = false;

    protected $fillable = [
        'session_uuid',
        'sh_user_id',
        'language',
        'status',
        'started_on',
        'last_activity_on',
        'ended_on',
        'end_reason',
        'turn_count',
        'title',
        'summary',
        'tags',
        'summary_status',
        'summary_error',
        'rating',
        'rated_on',
        'created_on',
        'modified_on',
        'is_deleted',
        'deleted_on',
    ];

    protected $casts = [
        'tags' => 'array',
        'turn_count' => 'integer',
        'rating' => 'integer',
        'is_deleted' => 'boolean',
        'started_on' => 'datetime',
        'last_activity_on' => 'datetime',
        'ended_on' => 'datetime',
        'rated_on' => 'datetime',
        'created_on' => 'datetime',
        'modified_on' => 'datetime',
        'deleted_on' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(HealthTalkMessage::class, 'session_id', 'session_id')->orderBy('seq');
    }

    public function qaPairs(): HasMany
    {
        return $this->hasMany(HealthTalkQaPair::class, 'session_id', 'session_id')->orderBy('seq');
    }
}
