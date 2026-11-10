<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The central users table (Aira users). The admin panel has no login, so this
 * model is only here because config/auth.php needs a user provider; the admin
 * reads users through the Aira API, not through this model.
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users'; // Explicitly point to the central table

    /**
     * The attributes that are mass assignable.
     * Match this with your API User model so both can create users properly.
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
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'auth0_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_on' => 'datetime',
            'token_expired_at' => 'datetime',
            'status' => 'boolean',
            'is_deleted' => 'boolean',
            'age' => 'integer',
            'created_by' => 'integer',
            'modified_by' => 'integer',
            'deleted_by' => 'integer',
        ];
    }
}