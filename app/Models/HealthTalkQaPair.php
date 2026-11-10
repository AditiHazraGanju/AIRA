<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthTalkQaPair extends Model
{
    protected $table = 'ht_qa_pairs';
    protected $primaryKey = 'qa_id';
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'seq',
        'question',
        'answer',
        'feedback',
        'feedback_on',
        'created_on',
    ];

    protected $casts = [
        'seq' => 'integer',
        'feedback_on' => 'datetime',
        'created_on' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(HealthTalkSession::class, 'session_id', 'session_id');
    }
}
