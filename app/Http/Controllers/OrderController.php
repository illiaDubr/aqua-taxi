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
            'delivery_option'    => 'required|in:home,entrance,coffee',

            // новое: что пришло с фронта
            'product_name'       => 'nullable|string|max:255',
            'water_type'         => 'required|in:silver,deep', // ← кодовое значение

            // координаты
            'lat'        => 'nullable|numeric|between:-90,90',
            'lng'        => 'nullable|numeric|between:-180,180',
            'latitude'   => 'nullable|numeric|between:-90,90',
            'longitude'  => 'nullable|numeric|between:-180,180',
        ]);

        if ($validated['delivery_time_type'] === 'custom' && empty($validated['custom_time'])) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => ['custom_time' => ['custom_time is required when delivery_time_type is custom']]
            ], 422);
        }

        // Нормализация координат
        $latitude  = $request->input('lat', $request->input('latitude'));
        $longitude = $request->input('lng', $request->input('longitude'));

        // Геокодинг при отсутствии координат
        if (($latitude === null || $longitude === null) && !empty($validated['address'])) {
            try {
                $geo = Http::withHeaders([
                    'User-Agent' => 'AquaTaxi/1.0 (admin@aqua-taxi.com)',
                ])->timeout(6)->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json', 'q' => $validated['address'], 'limit' => 1,
                ]);
                if ($geo->ok() && ($j = $geo->json()) && !empty($j)) {
                    $latitude  = $j[0]['lat'] ?? null;
                    $longitude = $j[0]['lon'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::error('GEO search error', ['e'=>$e->getMessage()]);
            }
        }

        // Реверс-геокодинг
        $addressToSave = $validated['address'] ?? null;
        if ((empty($addressToSave) || trim((string)$addressToSave) === '')
            && $latitude !== null && $longitude !== null) {

            try {
                $rev = Http::withHeaders([
                    'User-Agent' => 'AquaTaxi/1.0 (admin@aqua-taxi.com)',
                ])->timeout(6)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json', 'lat' => $latitude, 'lon' => $longitude,
                    'zoom' => 18, 'addressdetails' => 1,
                ]);

                if ($rev->ok() && ($r = $rev->json()) && !empty($r['display_name'])) {
                    $addressToSave = mb_substr($r['display_name'], 0, 255);
                }
            } catch (\Throwable $e) {
                Log::error('GEO reverse error', ['e'=>$e->getMessage()]);
            }

            if (empty($addressToSave)) {
                $addressToSave = sprintf('Manual point: %.5f, %.5f', (float)$latitude, (float)$longitude);
            }
        }

        // === Расчёт цены от water_type ===
        $basePrices = ['silver' => 120, 'deep' => 130];
        $base = $basePrices[$validated['water_type']] ?? 120;
        $qty  = (int) $validated['quantity'];

        switch ($validated['delivery_option']) {
            case 'home':
                $total = $qty * $base;
                break;

            case 'entrance':
                $total = (int) round($qty * $base * 0.8);
                break;

            case 'coffee':
                if ($qty < 5) {
                    return response()->json([
                        'message' => 'Для доставки в кав’ярню мінімальне замовлення — 5 бутлів'
                    ], 422);
                }
                $total = $qty * 70; // спец-тариф
                break;
        }

        // Сохранение заказа (добавили product_name и water_type)
        $order = Order::create([
            'user_id'            => Auth::id(),
            'address'            => $addressToSave,
            'latitude'           => $latitude !== null ? (float)$latitude : null,
            'longitude'          => $longitude !== null ? (float)$longitude : null,

            'product_name'       => $request->input('product_name'), // опционально
            'water_type'         => $validated['water_type'],        // 'silver' | 'deep'

            'quantity'           => $qty,
            'bottle_option'      => $validated['bottle_option'],
            'delivery_option'    => $validated['delivery_option'],
            'delivery_time_type' => $validated['delivery_time_type'],
            'custom_time'        => $validated['custom_time'] ?? null,
            'payment_method'     => $validated['payment_method'],
            'total_price'        => $total,
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

        event(new OrderStatusUpdated($order));
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

    public function activeOrders(Request $request)
    {
        $userId = $request->user()->id;

        $orders = Order::query()
            ->with(['driver:id,name,surname,phone'])
            ->where('user_id', $userId)
            ->whereIn('status', ['new', 'in_progress'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
    }
}
