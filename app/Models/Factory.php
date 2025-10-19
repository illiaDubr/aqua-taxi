<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Factory extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'email','phone','password','website',
        'warehouse_address','water_types',
        'certificate_path','certificate_status',
        'certificate_expiration','is_verified',
        'verified_until','lat','lng',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'water_types'   => 'array',
        'is_verified'   => 'boolean',
        'verified_until'=> 'datetime',
        'lat'           => 'float',
        'lng'           => 'float',
    ];

    // 🔹 важно: чтобы поле появлялось в JSON
    protected $appends = ['certificate_url'];

    // 🔹 аксессор
    public function getCertificateUrlAttribute(): ?string
    {
        $path = $this->certificate_path;
        if (!$path) {
            return null;
        }
        // вернёт /storage/.... (или https://.../storage/..., если в конфиге задан url)
        return Storage::disk('public')->url($path);
    }
}
