<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Driver extends Authenticatable {
    use Notifiable;

    protected $fillable = [
        'email',
        'phone',
        'password',
        'name',
        'surname',
        'balance',
    ];

    protected $hidden = [
        'password',
    ];
}
