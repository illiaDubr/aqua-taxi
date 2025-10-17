<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 1) Валидация
        $validated = $request->validate([
            'address' => 'required_without_all:lat,lng,latitude,longitude|string|max:255',
            'quantity' => 'required|integer|min:1',
            'bottle_option' => 'required|in:own,buy',
            'bottle_quality' => 'nullable|in:ideal,average,bad',
            'delivery_time_type' => 'required|in:now,custom',
            'custom_time' => 'nullable|date',
            'payment_method' => 'required|in:cash,card',
            'delivery_option' => 'required|in:home,entrance,coffee',

            'purchase_bottle_count' => 'nullable|integer|min:0',

            'product_name' => 'nullable|string|max:255',
            'water_type' => 'required|in:silver,deep',

            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validated['delivery_time_type'] === 'custom' && empty($validated['custom_time'])) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['custom_time' => ['custom_time is required when delivery_time_type is custom']],
            ], 422);
        }

        // 2) Нормализация координат
        $latitude = $request->input('lat', $request->input('latitude'));
        $longitude = $request->input('lng', $request->input('longitude'));

        // Геокодинг (если есть адрес, но нет координат)
        if (($latitude === null || $longitude === null) && !empty($validated['address'])) {
            try {
                $geo = Http::withHeaders([
                    'User-Agent' => 'AquaTaxi/1.0 (admin@aqua-taxi.com)',
                ])->timeout(6)->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $validated['address'],
                    'limit' => 1,
                ]);
                if ($geo->ok() && ($j = $geo->json()) && !empty($j)) {
                    $latitude = $j[0]['lat'] ?? null;
                    $longitude = $j[0]['lon'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::error('GEO search error', ['e' => $e->getMessage()]);
            }
        }

        // Реверс-геокодинг (если координаты есть, а адрес пуст)
        $addressToSave = $validated['address'] ?? null;
        if ((empty($addressToSave) || trim((string)$addressToSave) === '') && $latitude !== null && $longitude !== null) {
            try {
                $rev = Http::withHeaders([
                    'User-Agent' => 'AquaTaxi/1.0 (admin@aqua-taxi.com)',
                ])->timeout(6)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

                if ($rev->ok() && ($r = $rev->json()) && !empty($r['display_name'])) {
                    $addressToSave = mb_substr($r['display_name'], 0, 255);
                }
            } catch (\Throwable $e) {
                Log::error('GEO reverse error', ['e' => $e->getMessage()]);
            }

            if (empty($addressToSave)) {
                $addressToSave = sprintf('Manual point: %.5f, %.5f', (float)$latitude, (float)$longitude);
            }
        }

        // 3) Расчёт цены (1 в 1 как во фронте)
        $UNIT_PRICES = [
            'deep' => ['one' => 250, 'many' => 180],
            'silver' => ['one' => 260, 'many' => 190],
        ];
        $BUY_SURCHARGE = 350;
        $COFFEE_PRICE = 70;

        $qty = (int)$validated['quantity'];
        $wt = $validated['water_type'];
        $delivery = $validated['delivery_option'];
        $bottleOption = $validated['bottle_option'];

        $purchaseCount = $validated['purchase_bottle_count'] ?? ($bottleOption === 'buy' ? $qty : 0);

        if ($delivery === 'coffee') {
            if ($qty < 5) {
                return response()->json(['message' => 'Для доставки в кав’ярню мінімальне замовлення — 5 бутлів'], 422);
            }
            $unitWater = $COFFEE_PRICE;
        } else {
            $tier = $qty >= 2 ? 'many' : 'one';
            $unitWater = $UNIT_PRICES[$wt][$tier] ?? 0;
        }

        $discountFactor = $delivery === 'entrance' ? 0.8 : 1.0;

        $waterPart = (int)round($unitWater * $discountFactor) * $qty;
        $bottlePart = ($bottleOption === 'buy') ? ($BUY_SURCHARGE * $purchaseCount) : 0;
        $total = $waterPart + $bottlePart;

        // 4) Сохранение заказа

        $order = Order::create([
            'user_id' => Auth::id(),
            'address' => $addressToSave,
            'latitude' => $latitude !== null ? (float)$latitude : null,
            'longitude' => $longitude !== null ? (float)$longitude : null,

            'product_name' => $request->input('product_name'),
            'water_type' => $wt,

            'quantity' => $qty,
            'bottle_option' => $bottleOption,
            'bottle_quality' => $bottleOption === 'own' ? ($validated['bottle_quality'] ?? null) : null,
            'purchase_bottle_count' => $purchaseCount,

            'delivery_option' => $delivery,
            'delivery_time_type' => $validated['delivery_time_type'],
            'custom_time' => $validated['custom_time'] ?? null,
            'payment_method' => $validated['payment_method'],

            // итоговая сумма — сохраняем
            'total_price' => $total,
            'status' => 'new',
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
    public function complete(Request $request, Order $order)
    {
        $data = $request->validate([
            'rating' => ['nullable','integer','min:1','max:5'],
        ]);
        $rating = (int) ($data['rating'] ?? 5);

        // Авторизация: заказ принадлежит текущему пользователю
        $user = $request->user();
        if ((int)$order->user_id !== (int)$user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Разрешённые статусы к завершению
        if (!in_array($order->status, ['accepted','in_progress'], true)) {
            return response()->json(['message' => 'Order is not deliverable'], 422);
        }

        $updatedDriver = null;

        DB::transaction(function () use ($order, $rating, &$updatedDriver) {
            // 1) Лочим заказ
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, ['accepted','in_progress'], true)) {
                abort(409, 'Order status changed');
            }

            // 2) Списание бутлей у водителя, если клиент их ПОКУПАЛ
            if ($locked->driver_id && strtolower((string)$locked->bottle_option) === 'buy') {
                $qty = max(0, (int)$locked->quantity);

                // лочим запись водителя и атомарно уменьшаем количество
                DB::table('drivers')
                    ->where('id', $locked->driver_id)
                    ->lockForUpdate()
                    ->update([
                        'bottles' => DB::raw('GREATEST(bottles - '.((int)$qty).', 0)')
                    ]);

                // вернём обновлённые данные водителя
                $driverRow = DB::table('drivers')
                    ->select('id','bottles','balance')
                    ->where('id', $locked->driver_id)
                    ->first();

                if ($driverRow) {
                    $updatedDriver = [
                        'id'      => (int)$driverRow->id,
                        'bottles' => (int)$driverRow->bottles,
                        'balance' => (float)$driverRow->balance,
                    ];
                }
            }

            // 3) Завершаем заказ
            $locked->status = 'completed';
            $locked->rating = $rating;

            // пишем completed_at, если колонка существует
            if (Schema::hasColumn('orders', 'completed_at')) {
                $locked->completed_at = now();
            }

            $locked->save();
        });

        // отправим событие для фронто
        return response()->json([
            'message' => 'Order completed',
            'order'   => $order->fresh(),
            'driver'  => $updatedDriver, // null, если списание не применялось
        ], 200);
    }
}
