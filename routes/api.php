<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\OrderController;

use App\Http\Controllers\DriverAuthController;
use App\Http\Controllers\DriverOrderController;
use App\Http\Controllers\Driver\BalanceController;
use App\Http\Controllers\Driver\FondyController;

use App\Http\Controllers\FactoryAuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Admin\FactoryController;
use App\Http\Controllers\FactoryModerationController;
use App\Http\Controllers\FactoryOrderController;

/**
 * -------------------------
 * ВОДИТЕЛЬ: публичные (без авторизации)
 * -------------------------
 */
Route::prefix('driver')->group(function () {
    Route::post('register', [DriverAuthController::class, 'register']);
    Route::post('login',    [DriverAuthController::class, 'login']);
});

/**
 * -------------------------
 * ВОДИТЕЛЬ: приватные (через auth:sanctum)
 * -------------------------
 */
Route::prefix('driver')->middleware('auth:sanctum')->group(function () {
    // профиль (нужен фронту для bottles/balance)
    Route::get('profile', [DriverAuthController::class, 'profile']);

    // финансы
    Route::post('top-up', [BalanceController::class, 'topUp']);
    Route::get('balance', [BalanceController::class, 'getBalance']);
    Route::post('pay',    [FondyController::class, 'initialize']);

    // заказы
    Route::get('orders/new',    [DriverOrderController::class, 'newOrders']);
    Route::get('orders/active', [DriverOrderController::class, 'activeOrders']);
    Route::post('orders/{order}/accept', [DriverOrderController::class, 'accept']);

    // logout водителя
    Route::post('logout', [DriverAuthController::class, 'logout']);
});

// callback платёжки (публичный)
Route::post('/fondy/callback', [FondyController::class, 'callback'])->name('fondy.callback');

/**
 * -------------------------
 * ПОЛЬЗОВАТЕЛЬ (клиент)
 * -------------------------
 */
Route::prefix('user')->group(function () {
    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('login',    [UserAuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('logout', [UserAuthController::class, 'logout']);
});

// клиентские заказы
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/active', [OrderController::class, 'activeOrders']);
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete']);
});

/**
 * -------------------------
 * Broadcasting (если нужно)
 * -------------------------
 */
Broadcast::routes(['middleware' => ['auth:sanctum']]);

/**
 * -------------------------
 * Фабрики / Админка
 * -------------------------
 */
Route::prefix('factory')->group(function () {
    Route::post('/register', [FactoryAuthController::class, 'register']);
    Route::post('/login',    [FactoryAuthController::class, 'login']);
});

Route::get('/factories/verified',    [FactoryController::class, 'verified']);
Route::get('/factories/coordinates', [FactoryController::class, 'coordinates']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/factories/pending',              [FactoryController::class, 'index']);
    Route::post('/factories/{id}/approve',        [FactoryController::class, 'approve']);
    Route::post('/factories/{id}/reject',         [FactoryController::class, 'reject']);
    Route::post('/factories/{id}/moderate-certificate', [FactoryModerationController::class, 'moderateCertificate']);
    Route::get('/factories-with-certificates',    [FactoryModerationController::class, 'factoriesWithCertificates']);
});

// просмотр / модерация фабрик
Route::prefix('factories')->group(function () {
    Route::get('{id}',           [FactoryModerationController::class, 'show']);
    Route::put('{id}/approve',   [FactoryModerationController::class, 'approve']);
    Route::post('{id}/reject',   [FactoryModerationController::class, 'reject']);
});

// загрузка сертификата фабрики (под токеном пользователя фабрики)
Route::middleware('auth:sanctum')->post('/factory/upload-certificate', [FactoryAuthController::class, 'uploadCertificate']);

// заказы фабрики (для кабинета производителя)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/factory-orders', [FactoryOrderController::class, 'store']);
    Route::get('/factory-orders',  [FactoryOrderController::class, 'forFactory']);
});
