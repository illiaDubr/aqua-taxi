<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
// app/Models/Order.php
class Order extends Model
{
    protected $fillable = [
        'address',
        'quantity',
        'bottle_option',
        'delivery_time_type',
        'custom_time',
        'payment_method',
        'total_price',
        'user_id',
        'driver_id',
        'status',
        'latitude',
        'longitude',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }


}


