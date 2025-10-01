<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Factory extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'email',
        'phone',
        'password',
        'website',
        'warehouse_address',
        'water_types',
        'certificate_path',
        'certificate_status',
        'certificate_expiration',
        'is_verified',
        'verified_until',
        'lat',
        'lng', // ← ОБЯЗАТЕЛЬНО должно быть!
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_until' => 'date',
        'certificate_expiration' => 'date',
        'water_types' => 'array'
    ];
}
