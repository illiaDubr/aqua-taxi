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
        'water_types' => 'array',
        'is_verified' => 'boolean',
        'verified_until' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
    ];

    // ✅ Добавляем virtual-атрибут, который появится в JSON
    protected $appends = ['certificate_url'];

    // ✅ Генерация корректного публичного URL
    public function getCertificateUrlAttribute(): ?string
    {
        if (!$this->certificate_path) {
            return null;
        }

        // вернёт что-то вроде: https://example.com/storage/certificates/abc123.jpg
        return Storage::disk('public')->url($this->certificate_path);
    }
}
