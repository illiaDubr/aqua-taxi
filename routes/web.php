<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::post('/auth/request-code', [AuthController::class, 'requestCode']);
Route::post('/auth/verify-code', [AuthController::class, 'verifyCode']);
