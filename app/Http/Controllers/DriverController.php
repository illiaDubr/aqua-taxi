<?php

use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function setAvailability(Request $request)
    {
        $request->validate([
            'is_available' => 'required|boolean',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $user = $request->user();
        if ($user->role !== 'driver') {
            return response()->json(['message' => 'Not a driver'], 403);
        }

        $user->update([
            'is_available' => $request->is_available,
            'lat' => $request->lat,
            'lng' => $request->lng,
        ]);

        return response()->json(['message' => 'Status updated', 'driver' => $user]);
    }
    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json(['message' => 'Not a driver'], 403);
        }

        $user->update([
            'lat' => $request->lat,
            'lng' => $request->lng,
        ]);

        return response()->json(['message' => 'Location updated']);
    }
    public function getOrderLocation(Request $request, $orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('driver_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'lat' => $order->lat,
            'lng' => $order->lng,
            'address' => $order->address,
        ]);
    }

}
