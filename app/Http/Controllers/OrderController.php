<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use App\Events\OrderStatusUpdated;
use App\Events\NewOrderCreated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class OrderController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            // адрес обязателен ТОЛЬКО если нет координат
            'address'            => 'required_without_all:lat,lng,latitude,longitude|string|max:255',
            'quantity'           => 'required|integer|min:1',
            'bottle_option'      => 'required|in:own,buy',
            'delivery_time_type' => 'required|in:now,custom',
            'custom_time'        => 'nullable|date',
            'payment_method'     => 'required|in:cash,card',

            // координаты с фронта (любой вариант имён)
            'lat'        => 'nullable|numeric|between:-90,90',
            'lng'        => 'nullable|numeric|between:-180,180',
            'latitude'   => 'nullable|numeric|between:-90,90',
            'longitude'  => 'nullable|numeric|between:-180,180',

            'total_price'        => 'nullable|numeric|min:0',
        ]);

        if ($validated['delivery_time_type'] === 'custom' && empty($validated['custom_time'])) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['custom_time' => ['custom_time is required when delivery_time_type is custom']]
            ], 422);
        }

        // Нормализуем координаты
        $latitude  = $request->input('lat', $request->input('latitude'));
        $longitude = $request->input('lng', $request->input('longitude'));

        // Если координат нет — попробуем геокодить адрес (как раньше)
        if (($latitude === null || $longitude === null) && !empty($validated['address'])) {
            try {
                $geo = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => 'AquaTaxi/1.0 (admin@aqua-taxi.com)',
                ])->timeout(6)->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json', 'q' => $validated['address'], 'limit' => 1,
                ]);
                if ($geo->ok() && ($j = $geo->json()) && !empty($j)) {
                    $latitude  = $j[0]['lat'] ?? null;
                    $longitude = $j[0]['lon'] ?? null;
                }
            } catch (\Throwable $e) { \Log::error('GEO search error', ['e'=>$e->getMessage()]); }
        }

        // === КЛЮЧЕВАЯ ЧАСТЬ ===
        // Если адрес пустой, но есть координаты — реверс-геокодим,
        // а при неудаче — подставляем "Manual point: {lat}, {lng}".
        $addressToSave = $validated['address'] ?? null;
        if ((empty($addressToSave) || trim((string)$addressToSave) === '')
            && $latitude !== null && $longitude !== null) {

            try {
                $rev = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => 'AquaTaxi/1.0 (admin@aqua-taxi.com)',
                ])->timeout(6)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json', 'lat' => $latitude, 'lon' => $longitude,
                    'zoom' => 18, 'addressdetails' => 1,
                ]);

                if ($rev->ok() && ($r = $rev->json()) && !empty($r['display_name'])) {
                    $addressToSave = mb_substr($r['display_name'], 0, 255);
                }
            } catch (\Throwable $e) { \Log::error('GEO reverse error', ['e'=>$e->getMessage()]); }

            if (empty($addressToSave)) {
                $addressToSave = sprintf('Manual point: %.5f, %.5f', (float)$latitude, (float)$longitude);
            }
        }
        // === /КЛЮЧЕВАЯ ЧАСТЬ ===

        // Пересчёт цены на бэке
        $PRICE_PER_BOTTLE = 120;
        $total = (int)$validated['quantity'] * $PRICE_PER_BOTTLE;

        $order = \App\Models\Order::create([
            'address'            => $addressToSave, // всегда НЕ пустая строка к моменту сохранения
            'quantity'           => (int)$validated['quantity'],
            'bottle_option'      => $validated['bottle_option'],
            'delivery_time_type' => $validated['delivery_time_type'],
            'custom_time'        => $validated['custom_time'] ?? null,
            'payment_method'     => $validated['payment_method'],
            'total_price'        => $total,
            'user_id'            => \Illuminate\Support\Facades\Auth::id(),
            'latitude'           => $latitude !== null ? (float)$latitude : null,
            'longitude'          => $longitude !== null ? (float)$longitude : null,
        ]);

        return response()->json($order, 201);
    }



    public function complete(Request $request, Order $order)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5'
        ]);

        $order->update([
            'status' => 'completed',
            'rating' => $request->rating,
        ]);

        event(new \App\Events\OrderStatusUpdated($order));
        event(new NewOrderCreated($order));

        return response()->json(['message' => 'Order completed'], 200);
    }


    public function accept(Order $order)
    {
        $driver = auth()->user();

        $order->update([
            'status' => 'in_progress',
            'driver_id' => $driver->id,
        ]);

        $order->load('driver');

        broadcast(new OrderStatusUpdated($order))->toOthers();

        return response()->json([
            'message' => 'Order accepted',
            'order' => $order,
        ]);
    }




}
