<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    // MATIKAN fitur otomatis timestamp
    public $timestamps = false; 

    protected $fillable = [
        'hostname', 
        'api_key', 
        'ip_address', 
        'status'
    ];
}