<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryPayment extends Model
{
    protected $fillable = [
        'factory_id',
        'order_id',
        'pid',
        'amount',
        'amount_cents',
        'status',
        'credited',
        'response_data',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }
}


