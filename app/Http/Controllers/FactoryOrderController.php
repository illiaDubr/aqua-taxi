<?php

namespace App\Http\Controllers;

use App\Models\Factory;
use App\Models\FactoryOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Нормализуем water_type
        $needle = mb_strtolower(trim($data['water_type']));
        $price  = $this->resolvePricePerBottle($factory, $needle);

        if ($price === null) {
            return response()->json(['message' => 'Unsupported water_type for this factory'], 422);
        }

        $total = round($price * (int)$data['quantity'], 2);

        $order = FactoryOrder::create([
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
        $user = Auth::user();
        if (!$this->isFactory($user)) {
            return response()->json(['message' => 'Only factories can view this list'], 403);
        }

        $factoryId = $this->factoryIdFromUser($user);
        if (!$factoryId) {
            return response()->json(['message' => 'Factory is not linked to user'], 422);
        }

        [$statuses, $updatedSince] = $this->parseFilters($request);

        $q = FactoryOrder::query()->where('factory_id', $factoryId);

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
     * Водитель: его собственные заказы у фабрик.
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
    public function acceptByFactory(FactoryOrder $order)
    {
        $user = Auth::user();
        if (!$this->isFactory($user)) {
            return response()->json(['message' => 'Only factories can accept orders'], 403);
        }

        $factoryId = $this->factoryIdFromUser($user);
        if ((int)$order->factory_id !== (int)$factoryId) {
            return response()->json(['message' => 'Not your order'], 403);
        }

        if ($order->status !== 'new') {
            return response()->json(['message' => 'Order cannot be accepted in current status'], 422);
        }

        $order->status = 'accepted';
        $order->accepted_at = now();
        $order->save();

        return response()->json([
            'order' => $order->fresh(),
            'meta'  => ['server_time' => now()->toISOString()],
        ]);
    }

    /**
     * Производитель завершает заказ (accepted -> completed).
     */
    public function completeByFactory(FactoryOrder $order)
    {
        $user = Auth::user();
        if (!$this->isFactory($user)) {
            return response()->json(['message' => 'Only factories can complete orders'], 403);
        }

        $factoryId = $this->factoryIdFromUser($user);
        if ((int)$order->factory_id !== (int)$factoryId) {
            return response()->json(['message' => 'Not your order'], 403);
        }

        if ($order->status !== 'accepted') {
            return response()->json(['message' => 'Order cannot be completed in current status'], 422);
        }

        $order->status = 'completed';
        $order->completed_at = now();
        $order->save();

        return response()->json([
            'order' => $order->fresh(),
            'meta'  => ['server_time' => now()->toISOString()],
        ]);
    }

    // ========= ВСПОМОГАТЕЛЬНОЕ =========

    private function isDriver($user): bool
    {
        return $user && method_exists($user, 'isDriver')
            ? $user->isDriver()
            : (bool)($user->driver ?? false);
    }

    private function isFactory($user): bool
    {
        return $user && method_exists($user, 'isFactory')
            ? $user->isFactory()
            : (bool)($user->factory_id ?? false);
    }

    private function factoryIdFromUser($user): ?int
    {
        return isset($user->factory_id) && $user->factory_id
            ? (int)$user->factory_id
            : null;
    }

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
                // Игнорируем
            }
        }

        return [$statuses, $updatedSince];
    }

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
