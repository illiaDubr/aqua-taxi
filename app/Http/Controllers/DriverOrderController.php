<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        return $this->buildNewOrdersQuery($request)->get($this->orderFields());
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
}
