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

/*
|--------------------------------------------------------------------------
| DRIVER (публичные)
|--------------------------------------------------------------------------
*/
Route::prefix('driver')->group(function () {
    Route::post('register', [DriverAuthController::class, 'register']);
    Route::post('login',    [DriverAuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| DRIVER (приватные) — нужен токен с ability: driver
|--------------------------------------------------------------------------
*/
Route::prefix('driver')->middleware(['auth:sanctum','abilities:driver'])->group(function () {
    Route::get('profile', [DriverAuthController::class, 'profile']);
    Route::post('top-up', [BalanceController::class, 'topUp']);
    Route::get('balance', [BalanceController::class, 'getBalance']);
    Route::post('pay',    [FondyController::class, 'initialize']);

    Route::get('orders/new',             [DriverOrderController::class, 'newOrders']);
    Route::get('orders/active',          [DriverOrderController::class, 'activeOrders']);
    Route::post('orders/{order}/accept', [DriverOrderController::class, 'accept']);

    Route::post('logout', [DriverAuthController::class, 'logout']);
});

/* Платёжный callback — публичный */
Route::post('/fondy/callback', [FondyController::class, 'callback'])->name('fondy.callback');

/*
|--------------------------------------------------------------------------
| USER (публичные)
|--------------------------------------------------------------------------
*/
Route::prefix('user')->group(function () {
    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('login',    [UserAuthController::class, 'login']);
    Route::middleware(['auth:sanctum','abilities:user'])->post('logout', [UserAuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| USER ORDERS (приватные) — только ability: user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum','abilities:user'])->group(function () {
    Route::post('/orders',                  [OrderController::class, 'store']);
    Route::get('/orders/active',            [OrderController::class, 'activeOrders']);
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete']);
});

/* Broadcasting (по токену) */
Broadcast::routes(['middleware' => ['auth:sanctum']]);

/*
|--------------------------------------------------------------------------
| FACTORY / ADMIN (публичные)
|--------------------------------------------------------------------------
*/
Route::prefix('factory')->group(function () {
    Route::post('/register', [FactoryAuthController::class, 'register']);
    Route::post('/login',    [FactoryAuthController::class, 'login']);
});

Route::get('/factories/verified',    [FactoryController::class, 'verified']);
Route::get('/factories/coordinates', [FactoryController::class, 'coordinates']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| ADMIN (приватные) — рекомендуем ability: admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum','abilities:admin'])->group(function () {
    Route::get('/factories/pending',                    [FactoryController::class, 'index']);
    Route::post('/factories/{id}/approve',              [FactoryController::class, 'approve']);
    Route::post('/factories/{id}/reject',               [FactoryController::class, 'reject']);
    Route::post('/factories/{id}/moderate-certificate', [FactoryModerationController::class, 'moderateCertificate']);
    Route::get('/factories-with-certificates',          [FactoryModerationController::class, 'factoriesWithCertificates']);

    Route::post('/factories/{id}/unverify',             [FactoryModerationController::class, 'unverify']);
    Route::delete('/factories/{id}/certificate',        [FactoryModerationController::class, 'deleteCertificate']);
});

/*
|--------------------------------------------------------------------------
| FACTORY (приватные) — только ability: factory
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum','abilities:factory'])->group(function () {
    Route::post('/factory/upload-certificate',          [FactoryAuthController::class, 'uploadCertificate']);

    Route::get('/factory-orders',                       [FactoryOrderController::class, 'forFactory']);
    Route::post('/factory-orders/{order}/accept',       [FactoryOrderController::class, 'acceptByFactory']);
    Route::post('/factory-orders/{order}/complete',     [FactoryOrderController::class, 'completeByFactory']);
});

/*
|--------------------------------------------------------------------------
| FACTORY ORDERS, сторона водителя — только ability: driver
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum','abilities:driver'])->group(function () {
    Route::post('/factory-orders',   [FactoryOrderController::class, 'store']); // водитель создает
    Route::get('/factory-orders/mine',[FactoryOrderController::class, 'mine']); // водитель: мои заказы
});
