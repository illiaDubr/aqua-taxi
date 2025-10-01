<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryOrder extends Model
{
    use HasFactory;

    protected $table = 'factory_orders';

    protected $fillable = [
        'user_id',
        'driver_id',
        'factory_id',
        'water_type',
        'price_per_bottle',
        'quantity',
        'total_price',
        'status',
        'accepted_at',
        'completed_at',
    ];

    protected $casts = [
        'price_per_bottle' => 'float',
        'total_price'      => 'float',
        'quantity'         => 'integer',
        'accepted_at'      => 'datetime',
        'completed_at'     => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // 🔗 СВЯЗИ
    public function factory()
    {
        return $this->belongsTo(Factory::class); // FK: factory_id
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class); // FK: driver_id
    }

    public function user()
    {
        return $this->belongsTo(User::class); // FK: user_id (если нужно)
    }
}
