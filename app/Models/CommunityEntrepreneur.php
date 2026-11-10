<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityEntrepreneur extends Model
{
    protected $table = 'saathi_community_entrepreneur';
    protected $primaryKey = 'community_entrepreneur_id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'address',
        'mobile',
        'is_whatsapp',
        'dob',
        'registered_on',
        'aadhar',
        'id_number',
        'pan',
        'email',
        'gender',
        'community_id',
        'bank_details_id',
        'sh_user_id',
        'note',
        'device_id',
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
        'is_whatsapp' => 'boolean',
        'dob' => 'date',
        'registered_on' => 'datetime:Y-m-d H:i:s.u',
        'created_on' => 'datetime:Y-m-d H:i:s.u',
        'modified_on' => 'datetime:Y-m-d H:i:s.u',
        'is_deleted' => 'boolean',
        'deleted_on' => 'datetime:Y-m-d H:i:s.u',
        'is_active' => 'boolean',
    ];
}