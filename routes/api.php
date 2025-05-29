<?php

use App\Http\Controllers\DriverAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Driver\BalanceController;
use App\Http\Controllers\Driver\FondyController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\OrderController;

Route::prefix('driver')->group(function () {
    Route::post('register', [DriverAuthController::class, 'register']);
    Route::post('login', [DriverAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [DriverAuthController::class, 'logout']);
    });
});
Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
    Route::post('/top-up', [BalanceController::class, 'topUp']);
    Route::get('/balance', [BalanceController::class, 'getBalance']);
});
Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
    Route::post('/pay', [FondyController::class, 'initialize']);
});

Route::post('/fondy/callback', [FondyController::class, 'callback'])->name('fondy.callback');

Route::prefix('user')->group(function () {
    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('login', [UserAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->post('logout', [UserAuthController::class, 'logout']);
});
// routes/api.php
Route::middleware('auth:sanctum')->post('/orders', [OrderController::class, 'store']);

Route::middleware('auth:sanctum')->get('/orders/active', [OrderController::class, 'activeOrders']);

Route::middleware('auth:sanctum')->post('/orders/{order}/complete', [OrderController::class, 'complete']);

Route::middleware('auth:sanctum')->post('/orders/{order}/complete', [OrderController::class, 'complete']);

