<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthTalkMessage extends Model
{
    protected $table = 'ht_messages';
    protected $primaryKey = 'message_id';
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'seq',
        'role',
        'content',
        'input_mode',
        'interrupted',
        'metadata',
        'created_on',
    ];

    protected $casts = [
        'seq' => 'integer',
        'interrupted' => 'boolean',
        'metadata' => 'array',
        'created_on' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(HealthTalkSession::class, 'session_id', 'session_id');
    }
}
