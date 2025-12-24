<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpPolicy extends Model
{
    protected $table = 'ip_policies';
    protected $fillable = ['ip_address', 'action', 'reason'];

    // Tambahkan ini biar Laravel nggak maksa nyari kolom updated_at
    public $timestamps = false; 
}