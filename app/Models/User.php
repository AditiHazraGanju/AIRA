<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'mobile',
        'age',
        'gender',
        'pincode',
        'created_by',
        'modified_by',
        'status',
        'is_deleted',
        'deleted_on',
        'deleted_by',
        'auth0_token',
        'preferred_language',
        'token_expired_at',
        'auth0_id',
        'is_tara_user',
        'dob',
        'monthly_income_range',
        'id_number',
        'id_proof_file',
        'address',
        'landmark',
        'state_id',
        'state_name',
        'city',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'auth0_token',
        'id_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_on' => 'datetime',
        'token_expired_at' => 'datetime',
        'status' => 'boolean',
        'is_deleted' => 'boolean',
        'is_tara_user' => 'boolean',
        'dob' => 'date:Y-m-d',
        'state_id' => 'integer',
        'age' => 'integer',
        'created_by' => 'integer',
        'modified_by' => 'integer',
        'deleted_by' => 'integer',
        'password' => 'hashed',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
