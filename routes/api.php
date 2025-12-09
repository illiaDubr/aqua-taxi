<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\OrderController;

use App\Http\Controllers\DriverAuthController;
use App\Http\Controllers\DriverOrderController;
use App\Http\Controllers\Driver\BalanceController;

use App\Http\Controllers\DriverPaymentController;
use App\Http\Controllers\FactoryAuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Admin\FactoryController;
use App\Http\Controllers\FactoryModerationController;
use App\Http\Controllers\FactoryOrderController;
use App\Http\Controllers\FactoryPaymentController;
/*
|--------------------------------------------------------------------------
| DRIVER (публичные)
|--------------------------------------------------------------------------
*/
Route::prefix('driver')->group(function () {
    Route::post('register', [DriverAuthController::class, 'register']);
    Route::post('login',    [DriverAuthController::class, 'login']);
});

Route::middleware('auth:driver')->post('/driver/pay', [DriverPaymentController::class, 'initiatePayment']);
Route::get('/driver/pay/status/{paymentId}', [DriverPaymentController::class, 'checkStatus']);
Route::post('/driver/pay/callback', [DriverPaymentController::class, 'handleCallback']);
Route::post('/driver/pay/poll/{payment}', [DriverPaymentController::class, 'pollUntilSuccess']);
Route::get('/driver/payment/check', [DriverPaymentController::class, 'checkStatus']);

/*
|--------------------------------------------------------------------------
| DRIVER (приватные) — нужен токен с ability: driver
|--------------------------------------------------------------------------
*/
Route::prefix('driver')->middleware(['auth:sanctum','abilities:driver'])->group(function () {
    Route::get('profile', [DriverAuthController::class, 'profile']);
    Route::post('top-up', [BalanceController::class, 'topUp']);
    Route::get('balance', [BalanceController::class, 'getBalance']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'driverCancel']);
    Route::get('orders/new',             [DriverOrderController::class, 'newOrders']);
    Route::get('orders/active',          [DriverOrderController::class, 'activeOrders']);
    Route::post('orders/{order}/accept', [DriverOrderController::class, 'accept']);
    Route::get('/orders/cancelled', [DriverOrderController::class, 'cancelled']);
    Route::post('logout', [DriverAuthController::class, 'logout']);
});



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
Route::get('/driver/pay/good', [DriverPaymentController::class, 'handleGoodRedirect']);

/*
|--------------------------------------------------------------------------
| USER ORDERS (приватные) — только ability: user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum','abilities:user'])->group(function () {
    Route::post('/orders',                  [OrderController::class, 'store']);
    Route::get('/orders/active',            [OrderController::class, 'activeOrders']);
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
   
});





/* Broadcasting (по токену) */
Broadcast::routes(['middleware' => ['auth:sanctum']]);

/*
|--------------------------------------------------------------------------
| FACTORY / ADMIN (публичные)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum','abilities:admin'])->post('/logout', [AdminAuthController::class, 'logout']);

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
    Route::post('/logout', [FactoryAuthController::class, 'logout']);

    Route::get('/factory-orders',                       [FactoryOrderController::class, 'forFactory']);
    Route::post('/factory-orders/{order}/accept',       [FactoryOrderController::class, 'acceptByFactory']);
    Route::post('/factory-orders/{order}/complete',     [FactoryOrderController::class, 'completeByFactory']);
    Route::post('/factory/payment/initiate', [FactoryPaymentController::class, 'initiate']);
});


Route::post('/factory/payment/check', [FactoryPaymentController::class, 'check']);
/*
|--------------------------------------------------------------------------
| FACTORY ORDERS, сторона водителя — только ability: driver
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum','abilities:driver'])->group(function () {
    Route::post('/factory-orders',   [FactoryOrderController::class, 'store']); // водитель создает
    Route::get('/factory-orders/mine',[FactoryOrderController::class, 'mine']); // водитель: мои заказы
});
