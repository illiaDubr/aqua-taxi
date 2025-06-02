<?php

use App\Http\Controllers\DriverAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Driver\BalanceController;
use App\Http\Controllers\Driver\FondyController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DriverOrderController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\FactoryAuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Admin\FactoryController;
use App\Http\Controllers\Admin\FactoryModerationController;

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

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::middleware('auth:sanctum')->get('/driver/orders/new', [DriverOrderController::class, 'newOrders']);

Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
    Route::post('/orders/{order}/accept', [OrderController::class, 'accept']);
});
Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
    Route::get('/orders/active', [DriverOrderController::class, 'activeOrders']);
});

Route::prefix('factory')->group(function () {
    Route::post('/register', [FactoryAuthController::class, 'register']);
    Route::post('/login', [FactoryAuthController::class, 'login']);
});

Route::get('/factories/verified', [FactoryController::class, 'verified']);

Route::get('/factories/coordinates', [FactoryController::class, 'coordinates']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/factories/pending', [FactoryController::class, 'index']);
    Route::post('/factories/{id}/approve', [FactoryController::class, 'approve']);
    Route::post('/factories/{id}/reject', [FactoryController::class, 'reject']);
});


Route::prefix('factories')->group(function () {
    Route::get('{id}', [FactoryModerationController::class, 'show']);
    Route::put('{id}/approve', [FactoryModerationController::class, 'approve']);
    Route::post('{id}/reject', [FactoryModerationController::class, 'reject']);
});
