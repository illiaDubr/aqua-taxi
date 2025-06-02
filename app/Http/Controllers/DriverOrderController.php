<?php

namespace App\Http\Controllers;


use App\Models\Order;
use Illuminate\Http\Request;

class DriverOrderController extends Controller
{
public function index()
{
$orders = Order::where('status', 'new')
->orderByDesc('created_at')
->get();

return response()->json($orders);
}
    public function newOrders(Request $request)
    {
        return Order::where('status', 'new')->orderByDesc('created_at')->get();
    }

public function accept(Request $request, Order $order)
{
$driver = auth('driver')->user(); // убедись что guard:driver настроен

if ($order->status !== 'new') {
return response()->json(['error' => 'Цей товар вже прийнято іншим водієм'], 409);
}

$order->update([
'status' => 'in_progress',
'driver_id' => $driver->id,
]);

event(new \App\Events\OrderStatusUpdated($order));

return response()->json(['message' => 'Замовлення прийнято'], 200);
}

    public function activeOrders(Request $request)
    {
        return \App\Models\Order::with('user')
            ->where('driver_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->orderByDesc('created_at')
            ->get();
    }

}
