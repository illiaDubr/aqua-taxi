<?php
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'type' => 'required|in:silver,deep_clean',
            'bottles_count' => 'required|integer|min:1',
            'address' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'scheduled_at' => 'nullable|date',
        ]);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'bottles_count' => $request->bottles_count,
            'address' => $request->address,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'scheduled_at' => $request->scheduled_at,
        ]);

        return response()->json(['message' => 'Order created', 'order' => $order]);
    }

    public function active(Request $request)
    {
        $order = $request->user()->orders()
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->latest()
            ->first();

        return response()->json(['order' => $order]);
    }
    public function availableOrders(Request $request)
    {
        $orders = Order::where('status', 'pending')
            ->whereNull('driver_id')
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    public function acceptOrder(Request $request, $id)
    {
        $order = Order::where('id', $id)
            ->where('status', 'pending')
            ->whereNull('driver_id')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not available'], 404);
        }

        $order->update([
            'driver_id' => $request->user()->id,
            'status' => 'accepted',
        ]);

        return response()->json(['message' => 'Order accepted', 'order' => $order]);
    }

    public function completeOrder(Request $request, $id)
    {
        $order = Order::where('id', $id)
            ->where('driver_id', $request->user()->id)
            ->where('status', 'accepted')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found or not active'], 404);
        }

        $order->update([
            'status' => 'delivered',
        ]);

        return response()->json(['message' => 'Order completed']);
    }
    public function clientHistory(Request $request)
    {
        $orders = $request->user()->orders()
            ->whereIn('status', ['delivered', 'cancelled'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['orders' => $orders]);
    }
    public function driverHistory(Request $request)
    {
        $orders = Order::where('driver_id', $request->user()->id)
            ->whereIn('status', ['delivered', 'cancelled'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['orders' => $orders]);
    }
    public function getDriverLocation(Request $request, $orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->whereNotNull('driver_id')
            ->firstOrFail();

        $driver = $order->driver;

        return response()->json([
            'lat' => $driver->lat,
            'lng' => $driver->lng,
        ]);
    }

}
