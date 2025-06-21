<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
class Factory extends Authenticatable
{
    use HasApiTokens;
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
