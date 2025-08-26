<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Order extends Model
{
    use HasFactory, Notifiable;

    // если у тебя таблица называется "orders", это можно не указывать
    // protected $table = 'orders';

    protected $fillable = [
        'address',
        'quantity',
        'bottle_option',          // own | buy
        'delivery_time_type',     // now | custom
        'custom_time',
        'payment_method',         // cash | card
        'total_price',
        'user_id',
        'driver_id',
        'status',                 // new | in_progress | completed | cancelled
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'quantity'     => 'integer',
        'custom_time'  => 'datetime',
        'total_price'  => 'decimal:2',
        'latitude'     => 'float',
        'longitude'    => 'float',
    ];

    // дефолтный статус
    protected $attributes = [
        'status' => 'new',
    ];

    /* ---------- СКОУПЫ ---------- */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['new', 'in_progress']);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('driver_id')
            ->whereIn('status', ['new', 'in_progress']);
    }

    /* ---------- УДОБНЫЕ АТРИБУТЫ ---------- */

    // Получить координаты одним полем: $order->coordinates => [lat, lng] | null
    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }
        return [(float)$this->latitude, (float)$this->longitude];
    }

    // Опционально: метод-хелпер для установки координат
    public function setCoordinates(?float $lat, ?float $lng): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
    }

    /* ---------- СВЯЗИ ---------- */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
