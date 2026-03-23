<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DriverOrderController extends Controller
{
    /**
     * СТАРЫЙ РОУТ: список "новых" заказов (оставляем для обратной совместимости).
     */
    public function index(Request $request)
    {
        return $this->buildNewOrdersQuery($request)->get($this->orderFields());
    }

    /**
     * Новые заказы для карты водителя.
     * Параметры:
     *  - ?water=silver|deep|Срібна|Глибокого очищення
     *  - ?with_coords=0|1 (по умолчанию 1)
     */
    public function newOrders(Request $request)
    {
        $driver = $this->driver($request);
        $orders = $this->buildNewOrdersQuery($request)->get($this->orderFields());

        // Добавляем расстояние до заказа, если у водителя есть координаты
        if ($driver->last_lat && $driver->last_lng) {
            $orders = $orders->map(function ($order) use ($driver) {
                $lat = $order->latitude ?? $order->lat;
                $lng = $order->longitude ?? $order->lng;
                if ($lat && $lng) {
                    $order->distance_km = $this->calculateDistance($driver->last_lat, $driver->last_lng, $lat, $lng);
                } else {
                    $order->distance_km = null;
                }
                return $order;
            });
        }

        return $orders;
    }

    /**
     * Активные заказы конкретного водителя (accepted|in_progress).
     * Отдаем также пользователя (имя/телефон).
     */
    public function activeOrders(Request $request)
    {
        $driver = $this->driver($request);

        return Order::query()
            ->with(['user:id,name,surname,phone'])
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['accepted','in_progress'])
            // вернуть только те, у кого есть какие-то координаты (любой формат)
            ->where(function ($q) {
                $q->where(function ($q1) {
                    $q1->whereNotNull('latitude')->whereNotNull('longitude');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('lat')->whereNotNull('lng');
                });
            })
            ->orderByDesc('created_at')
            ->get($this->orderFields(['user_id']));
    }


    /**
     * Взять заказ в работу.
     */
    public function accept(Request $request, Order $order)
    {
        $driver = $this->driver($request);

        try {
            DB::transaction(function () use ($order, $driver) {
                $fresh = Order::where('id', $order->id)->lockForUpdate()->first();

                if (!$fresh || $fresh->status !== 'new') {
                    abort(409, 'Цей товар вже прийнято іншим водієм');
                }

                // можно ставить accepted или сразу in_progress.
                // оставляю как у тебя — сразу in_progress:
                $fresh->update([
                    'status'    => 'in_progress',
                    'driver_id' => $driver->id,
                ]);

                event(new OrderStatusUpdated($fresh));

                try {
                    $nodeWebhookUrl = config('services.nodesocket.host') . '/webhooks/order-status';
                    $nodeWebhookToken = config('services.nodesocket.token');
                    Http::timeout(3)
                        ->withHeaders(['X-Node-Socket-Token' => $nodeWebhookToken])
                        ->post($nodeWebhookUrl, [
                            'status' => 'in_progress',
                            'order' => $fresh->toArray(),
                        ]);
                } catch (\Throwable $e) {
                    Log::warning('Node websocket order-status webhook failed', ['error' => $e->getMessage()]);
                }
            });

            return response()->json(['message' => 'Замовлення прийнято'], 200);
        } catch (\Throwable $e) {
            if ((int)$e->getCode() === 409) {
                return response()->json(['error' => 'Цей товар вже прийнято іншим водієм'], 409);
            }
            report($e);
            return response()->json(['error' => 'Помилка при прийнятті замовлення'], 500);
        }
    }

    // ------------------- приватные помощники -------------------

    /**
     * Общая сборка запроса для "новых" заказов (status=new) с фильтрами.
     */
    private function buildNewOrdersQuery(Request $request)
    {
        $query = Order::query()
            ->where('status', 'new')
            ->orderByDesc('created_at');

        // Только заказы с координатами (по умолчанию да)
        $withCoords = $request->boolean('with_coords', true);
        if ($withCoords) {
            // 👇 учитываем оба формата координат: latitude/longitude ИЛИ lat/lng
            $query->where(function ($q) {
                $q->where(function ($q1) {
                    $q1->whereNotNull('latitude')
                        ->whereNotNull('longitude');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('lat')
                        ->whereNotNull('lng');
                });
            });
        }

        // Фильтр по типу воды
        if ($water = $request->query('water')) {
            $norm = $this->normalizeWater($water);
            if ($norm !== null) {
                // в БД храним коды: silver|deep
                $query->where('water_type', $norm);
                // если в новых записях water_type пустой, но ждетcя product_name —
                // фильтрация по water_type просто никого не найдет; это ок —
                // фронт также умеет распознавать тип из product_name.
            }
        }

        return $query;
    }

    /**
     * Набор полей, которые реально нужны на карте/в попапе.
     * Добавлены lat/lng (для нового формата).
     */
    private function orderFields(array $extra = [])
    {
        return array_values(array_unique(array_merge([
            'id',
            'product_name',
            'address',
            'quantity',
            'payment_method',
            'total_price',
            'latitude',
            'longitude',
            'lat',        // 👈 новый формат
            'lng',        // 👈 новый формат
            'water_type',
            'delivery_option',
            'bottle_option',
            'bottle_quality',
            'purchase_bottle_count',
            'status',
            'driver_id',
            'created_at',
        ], $extra)));
    }

    /**
     * Приводим входной water к silver/deep. Принимаем и локализованные названия.
     */
    private function normalizeWater(string $value): ?string
    {
        $v = mb_strtolower(trim($value));
        if ($v === 'silver' || str_contains($v, 'срібн') || str_contains($v, 'серебр')) return 'silver';
        if ($v === 'deep'   || str_contains($v, 'глибок') || str_contains($v, 'глубок')) return 'deep';
        return null;
    }

    /**
     * Получить текущего водителя из guard'а.
     * Роуты должны быть под middleware('auth:driver') или sanctum с abilities:driver.
     */
    private function driver(Request $request)
    {
        $driver = $request->user() ?: Auth::guard('driver')->user();
        abort_if(!$driver, 401, 'Необхідна авторизація водія');
        return $driver;
    }

    /**
     * Расчет расстояния между двумя точками (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    public function profile(Request $request)
    {
        /** @var \App\Models\Driver $driver */
        $driver = $request->user(); // auth:driver
        return response()->json([
            'id'       => $driver->id,
            'name'     => $driver->name,
            'surname'  => $driver->surname,
            'phone'    => $driver->phone,
            'bottles'  => (int)($driver->bottles ?? 0),
            'balance'  => (float)($driver->balance ?? 0),
        ]);
    }
    public function cancelled(Request $request)
    {
        $driver = $request->user();
        $since = $request->query('since');

        try {
            $sinceDt = $since ? Carbon::parse($since) : Carbon::now()->subMinutes(5);
        } catch (\Exception $e) {
            $sinceDt = Carbon::now()->subMinutes(5);
        }

        $orders = Order::query()
            ->where('driver_id', $driver->id)
            ->whereNotNull('cancelled_at')
            ->where('cancelled_at', '>=', $sinceDt)
            ->orderBy('cancelled_at', 'desc')
            ->get([
                'id','address','quantity','cancelled_at','cancelled_by','cancel_reason','status','total_price','latitude','longitude'
            ]);

        return response()->json($orders);
    }
}
