<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverPayment extends Model
{
    protected $fillable = [
        'driver_id',
        'order_id',
        'amount',
        'status',
        'response_data',
    ];

    protected $casts = [
        'response_data' => 'array',
        'amount' => 'float',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
