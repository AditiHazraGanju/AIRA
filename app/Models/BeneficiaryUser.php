<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiaryUser extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'saathi_beneficiary';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'beneficiary_id';

    public $timestamps = false;   // tell Laravel not to expect created_at & updated_at

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'mobile',
        'is_whatsapp',
        'related_to_beneficiary_id',
        'bu_type',
        'address',
        'aadhar',
        'id_number',
        'pin',
        'dob',
        'gender',
        'yearly_income',
        'monthly_income_range',
        'community_id',
        'bank_details_id',
        'customer_segregation_tags',
        'sh_user_id',
        'note',
        'state_id',
        'state_name',
        'city',
        'landmark',
        'created_on',
        'modified_on',
        'created_by',
        'modified_by',
        'is_deleted',
        'deleted_on',
        'deleted_by',
        'is_active',
        'unique_id',             // ← NEW: 12-digit system-generated unique identifier
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_whatsapp' => 'boolean',
        'dob' => 'date:Y-m-d',
        'yearly_income' => 'decimal:2',
        'created_on' => 'datetime:Y-m-d H:i:s.u',
        'modified_on' => 'datetime:Y-m-d H:i:s.u',
        'is_deleted' => 'boolean',
        'deleted_on' => 'datetime:Y-m-d H:i:s.u',
        'is_active' => 'boolean',
        'unique_id' => 'string',  // ← NEW: cast as string to preserve exact 12-digit representation
    ];
}