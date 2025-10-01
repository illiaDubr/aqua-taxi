<?php

namespace App\Http\Controllers;

use App\Models\Factory;
use App\Models\FactoryOrder;
use App\Models\Driver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
class FactoryOrderController extends Controller
{
    /**
     * Водитель создаёт заказ у производителя.
     * Тело: { factory_id, water_type, quantity }
     * Цена берётся на бэке из Factory.water_types (JSON).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'factory_id' => ['required','integer','exists:factories,id'],
            'water_type' => ['required','string','max:100'],
            'quantity'   => ['required','integer','min:1','max:1000'],
        ]);

        $user = Auth::user();
        if (!$this->isDriver($user)) {
            return response()->json(['message' => 'Only drivers can create factory orders'], 403);
        }

        $factory = Factory::select('id','water_types')->findOrFail($data['factory_id']);

        $needle = mb_strtolower(trim($data['water_type']));
        $price  = $this->resolvePricePerBottle($factory, $needle);
        if ($price === null) {
            return response()->json(['message' => 'Unsupported water_type for this factory'], 422);
        }

        $total = round($price * (int)$data['quantity'], 2);

        $order = FactoryOrder::create([
            'user_id'          => null,            // ← добавили
            'driver_id'        => $user->id,
            'factory_id'       => $factory->id,
            'water_type'       => $needle,
            'price_per_bottle' => $price,
            'quantity'         => (int)$data['quantity'],
            'total_price'      => $total,
            'status'           => 'new',
        ]);

        return response()->json([
            'success' => true,
            'order'   => $order,
            'meta'    => ['server_time' => now()->toISOString()],
        ], 201);
    }

    /**
     * Производитель: список заказов (polling).
     * Пример: /factory-orders?status=new,accepted&updated_since=2025-10-01T10:00:00Z
     */
    public function forFactory(Request $request)
    {
        $user = $request->user();

        // Защита от неправильного типа токена/отсутствия пользователя
        if (!$user instanceof Factory) {
            return response()->json(['message' => 'Only factories can view this list'], 403);
        }

        $status = $request->query('status');
        $since  = $request->query('updated_since');

        $q = Order::query();

        if ($status === 'new') {
            // Новые заказы, которые ещё никем не приняты
            $q->where('status', 'new');
        } elseif ($status === 'accepted') {
            // Принятые ТЕКУЩЕЙ фабрикой
            $q->where('status', 'accepted')
                ->where('factory_id', $user->id);
        } elseif ($status !== null) {
            return response()->json(['message' => 'Unknown status'], 422);
        }

        if ($since) {
            // допускаем ISO-строку; БД сама приведёт
            $q->where('updated_at', '>=', $since);
        }

        $orders = $q->orderByDesc('id')->get();

        return response()->json([
            'orders' => $orders,
            'meta'   => ['server_time' => now()->toISOString()],
        ]);
    }

    /**
     * Водитель: свои заказы у фабрик.
     */
    public function mine(Request $request)
    {
        $user = Auth::user();
        if (!$this->isDriver($user)) {
            return response()->json(['message' => 'Only drivers can view this list'], 403);
        }

        [$statuses, $updatedSince] = $this->parseFilters($request);

        $q = FactoryOrder::query()->where('driver_id', $user->id);

        if ($statuses) {
            $q->whereIn('status', $statuses);
        }
        if ($updatedSince) {
            $q->where('updated_at', '>=', $updatedSince);
        }

        $orders = $q->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'orders' => $orders,
            'meta'   => ['server_time' => now()->toISOString()],
        ]);
    }

    /**
     * Производитель принимает заказ (new -> accepted).
     */
    public function acceptByFactory(Request $request, Order $order)
    {
        $user = $request->user();
        if (!$user instanceof Factory) {
            return response()->json(['message' => 'Only factories can accept orders'], 403);
        }

        if ($order->status !== 'new') {
            return response()->json(['message' => 'Order is not new'], 422);
        }

        DB::transaction(function () use ($order, $user) {
            $order->status      = 'accepted';
            $order->factory_id  = $user->id;
            $order->accepted_at = now();
            $order->save();
        });

        return response()->json(['message' => 'Accepted', 'order' => $order->fresh()]);
    }


    /**
     * Производитель завершает заказ (accepted -> completed).
     */
    public function completeByFactory(Request $request, Order $order)
    {
        $user = $request->user();
        if (!$user instanceof Factory) {
            return response()->json(['message' => 'Only factories can complete orders'], 403);
        }

        if (!($order->status === 'accepted' && (int)$order->factory_id === (int)$user->id)) {
            return response()->json(['message' => 'Order not accepted by this factory'], 422);
        }

        DB::transaction(function () use ($order) {
            $order->status        = 'completed';
            $order->completed_at  = now();
            $order->save();
        });

        return response()->json(['message' => 'Completed', 'order' => $order->fresh()]);
    }

    // ========= ВСПОМОГАТЕЛЬНОЕ =========

    private function isDriver($user): bool
    {
        if (!$user) return false;
        if (method_exists($user, 'isDriver')) return (bool)$user->isDriver();
        // токен выписан на модель Driver или есть флаг/связь driver
        return $user instanceof Driver || (bool)($user->driver ?? false);
    }

    private function isFactory($user): bool
    {
        if (!$user) return false;
        if (method_exists($user, 'isFactory')) return (bool)$user->isFactory();
        // ВАЖНО: если аутентифицирована именно модель Factory — это и есть производитель
        return $user instanceof Factory || isset($user->factory_id);
    }

    private function factoryIdFromUser($user): ?int
    {
        if (!$user) return null;

        // Если это сама модель Factory (когда логинимся именно фабрикой)
        if ($user instanceof Factory) {
            return (int)$user->id;
        }

        // Если используешь "общую" модель User с полем factory_id
        if (isset($user->factory_id) && $user->factory_id) {
            return (int)$user->factory_id;
        }

        return null;
    }

    /**
     * Возвращает [array|null $statuses, CarbonImmutable|null $updatedSince]
     */
    private function parseFilters(Request $request): array
    {
        $statuses = null;
        if ($request->filled('status')) {
            $statuses = collect(explode(',', $request->string('status')))
                ->map(fn($s) => trim($s))
                ->filter()
                ->values()
                ->all();
        }

        $updatedSince = null;
        if ($request->filled('updated_since')) {
            try {
                $updatedSince = CarbonImmutable::parse($request->string('updated_since'));
            } catch (\Throwable $e) {
                // игнор
            }
        }

        return [$statuses, $updatedSince];
    }

    /**
     * Извлекаем цену по water_type из JSON поля фабрики.
     * Ожидаемый формат в Factory.water_types:
     * [
     *   { "code": "silver", "name": "Срібна", "price": 33.5 },
     *   { "code": "deep",   "name": "Глибокого очищення", "price": 28.0 }
     * ]
     */
    private function resolvePricePerBottle(Factory $factory, string $waterType): ?float
    {
        $raw = $factory->water_types;
        if (!$raw) return null;

        $arr = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($arr)) return null;

        foreach ($arr as $item) {
            $code = isset($item['code']) ? mb_strtolower((string)$item['code']) : null;
            $name = isset($item['name']) ? mb_strtolower((string)$item['name']) : null;
            if (($code && $code === $waterType) || ($name && $name === $waterType)) {
                return isset($item['price']) ? round((float)$item['price'], 2) : null;
            }
        }
        return null;
    }
}
