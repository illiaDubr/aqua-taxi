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
            'address' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'bottle_option' => 'required|in:own,buy',
            'delivery_time_type' => 'required|in:now,custom',
            'custom_time' => 'nullable|date',
            'payment_method' => 'required|in:cash,card',
            'total_price' => 'required|numeric',
        ]);

        // 🛰 Получаем координаты через Nominatim
        $lat = null;
        $lon = null;

        try {
            $geo = Http::withHeaders([
                'User-Agent' => 'AquaTaxi/1.0 (admin@aqua-taxi.com)'
            ])->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'json',
                'q' => $validated['address'],
                'limit' => 1,
            ]);

            $response = $geo->json();
            Log::info('GEO API response', ['response' => $response]);

            if ($geo->ok() && !empty($response)) {
                $lat = $response[0]['lat'] ?? null;
                $lon = $response[0]['lon'] ?? null;
            } else {
                Log::warning('GEO API returned empty or invalid response', ['address' => $validated['address']]);
            }
        } catch (\Throwable $e) {
            Log::error('GEO API error', ['exception' => $e]);
        }

        // 📝 Создаем заказ
        $order = Order::create([
            ...$validated,
            'user_id' => Auth::id(),
            'latitude' => $lat,
            'longitude' => $lon,
        ]);


        return response()->json($order, 201);
    }

    public function activeOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::with('driver') // 👈 добавили
        ->where('user_id', $user->id)
            ->whereIn('status', ['new', 'in_progress'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
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
