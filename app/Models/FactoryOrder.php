<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'factory_id',
        'water_type',
        'price_per_bottle',
        'quantity',
        'total_price',
        'status',
    ];
    protected $casts = [
        'water_types' => 'array',
    ];
}
