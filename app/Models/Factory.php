<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Factory extends Authenticatable
{
    protected $fillable = [
        'email', 'phone', 'password', 'website',
        'warehouse_address', 'water_types',
        'certificate_path', 'is_verified', 'verified_until',
        'lat', 'lng',
    ];


    protected $casts = [
        'is_verified' => 'boolean',
        'verified_until' => 'date',
    ];
}
