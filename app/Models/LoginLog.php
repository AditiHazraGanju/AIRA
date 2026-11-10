<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'saathi_login_log';
    protected $primaryKey = 'login_log_id';
    public $timestamps = false;

    protected $fillable = [
        'mobile_no',
        'otp',
        'valid_upto',
        'retry_count',
        'login_datetime',
        'otp_msg_type',
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
        'otp' => 'integer',
        'retry_count' => 'integer',
        'is_deleted' => 'boolean',
        'is_active' => 'boolean',
        'valid_upto' => 'datetime',
        'login_datetime' => 'datetime',
        'created_on' => 'datetime',
        'modified_on' => 'datetime',
        'deleted_on' => 'datetime',
    ];
}
