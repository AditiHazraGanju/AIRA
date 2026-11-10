<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $table = 'saathi_states';
    protected $primaryKey = 'state_id';
    public $timestamps = false;

    protected $fillable = [
        'state_name',
        'state_code',
        'is_active',
        'created_on',
        'modified_on'
    ];
}
