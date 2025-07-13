<?php

namespace App\Http\Controllers;

use App\Models\FactoryOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FactoryOrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'factory_id' => 'required|exists:factories,id',
            'water_type' => 'required|string',
            'price_per_bottle' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        $total = $request->price_per_bottle * $request->quantity;

        $order = FactoryOrder::create([
            'user_id' => Auth::id(),
            'factory_id' => $request->factory_id,
            'water_type' => $request->water_type,
            'price_per_bottle' => $request->price_per_bottle,
            'quantity' => $request->quantity,
            'total_price' => $total,
        ]);

        return response()->json(['success' => true, 'order' => $order]);
    }

    public function forFactory()
    {
        $factoryId = auth()->user()->factory_id; // если у производителя есть user_id
        return FactoryOrder::where('factory_id', $factoryId)->get();
    }
}
