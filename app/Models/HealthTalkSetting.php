<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton key/value settings store. Each row's setting_value is a JSON
 * object keyed by language code (ENG/HIN/MAR/BEN), matching the same
 * multilingual-JSON convention used by MyBanner.title etc.
 *
 * Rows in use: 'guardrail_default_message', 'start_pitch'.
 */
class HealthTalkSetting extends Model
{
    protected $table = 'ht_settings';
    protected $primaryKey = 'setting_id';
    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'modified_on',
        'modified_by',
    ];

    protected $casts = [
        'setting_value' => 'array',
        'modified_on' => 'datetime',
    ];

    public static function getValue(string $key): array
    {
        return static::where('setting_key', $key)->value('setting_value') ?? [];
    }
}
