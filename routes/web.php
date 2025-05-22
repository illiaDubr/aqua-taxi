<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return 'Laravel is alive';
})->where('any', '.*');
Route::post('/auth/request-code', [AuthController::class, 'requestCode']);
Route::post('/auth/verify-code', [AuthController::class, 'verifyCode']);
Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::patch('/driver/availability', [DriverController::class, 'setAvailability']);
});
Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
    Route::post('/orders', [OrderController::class, 'create']);
    Route::get('/orders/active', [OrderController::class, 'active']);
});
Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::get('/driver/orders/available', [OrderController::class, 'availableOrders']);
    Route::patch('/driver/orders/{id}/accept', [OrderController::class, 'acceptOrder']);
    Route::patch('/driver/orders/{id}/complete', [OrderController::class, 'completeOrder']);
});
// Клиент
Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
    Route::get('/orders/history', [OrderController::class, 'clientHistory']);
});

// Водитель
Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::get('/driver/orders/history', [OrderController::class, 'driverHistory']);
});
Route::patch('/driver/location', [DriverController::class, 'updateLocation']);
Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
    Route::get('/orders/{id}/driver-location', [OrderController::class, 'getDriverLocation']);
});
Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::get('/driver/orders/{id}/location', [OrderController::class, 'getOrderLocation']);
});


