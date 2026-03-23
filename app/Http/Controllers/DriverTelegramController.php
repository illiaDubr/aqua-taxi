<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DriverTelegramController extends Controller
{
    // Генерация кода для привязки Telegram (для водителя)
    public function generateLinkCode(Request $request)
    {
        $driver = $request->user(); // auth:driver

        $code = Str::random(8);
        Cache::put("telegram_link_{$code}", $driver->id, now()->addMinutes(10));

        return response()->json([
            'code' => $code,
            'message' => 'Отправьте этот код боту в Telegram: /attach ' . $code
        ]);
    }

    // Webhook от бота для привязки
    public function attach(Request $request)
    {
        $code = $request->input('code');
        $telegramId = $request->input('telegram_id');

        if (!$code || !$telegramId) {
            return response()->json(['error' => 'Missing code or telegram_id'], 400);
        }

        $driverId = Cache::get("telegram_link_{$code}");
        if (!$driverId) {
            return response()->json(['error' => 'Invalid or expired code'], 400);
        }

        $driver = Driver::find($driverId);
        if (!$driver) {
            return response()->json(['error' => 'Driver not found'], 404);
        }

        // Проверяем, не привязан ли уже этот telegram_id
        $existing = Driver::where('telegram_id', $telegramId)->first();
        if ($existing && $existing->id !== $driver->id) {
            return response()->json(['error' => 'Telegram account already linked to another driver'], 409);
        }

        $driver->update(['telegram_id' => $telegramId]);
        Cache::forget("telegram_link_{$code}");

        return response()->json(['message' => 'Telegram account linked successfully']);
    }

    // Webhook от бота для принятия заказа
    public function acceptOrder(Request $request)
    {
        $telegramId = $request->input('telegram_id');
        $orderId = $request->input('order_id');
        $action = $request->input('action'); // 'accept' or 'reject'

        if (!$telegramId || !$orderId || !in_array($action, ['accept', 'reject'])) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        $driver = Driver::where('telegram_id', $telegramId)->first();
        if (!$driver) {
            return response()->json(['error' => 'Driver not found'], 404);
        }

        if ($action === 'reject') {
            return response()->json(['message' => 'Order rejected']);
        }

        // Имитируем принятие заказа через DriverOrderController
        $orderController = new DriverOrderController();
        $fakeRequest = new Request();
        $fakeRequest->setUserResolver(function () use ($driver) {
            return $driver;
        });
        $fakeRequest->merge(['order' => $orderId]);

        try {
            $response = $orderController->accept($fakeRequest, \App\Models\Order::find($orderId));
            return $response;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Обновление локации водителя
    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $driver = $request->user();
        $driver->update([
            'last_lat' => $request->lat,
            'last_lng' => $request->lng,
        ]);

        return response()->json(['message' => 'Location updated']);
    }

    // Получение водителей с привязанным Telegram (для Node.js)
    public function getDriversWithTelegram(Request $request)
    {
        // Проверка токена
        $token = $request->header('X-Node-Socket-Token');
        if ($token !== config('services.nodesocket.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $drivers = Driver::whereNotNull('telegram_id')->select('id', 'telegram_id', 'name')->get();
        return response()->json($drivers);
    }
}
