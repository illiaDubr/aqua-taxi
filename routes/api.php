<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DriverAuthController;

Route::post('/driver/register', [DriverAuthController::class, 'register']);
