<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BalanceController extends Controller
{
    public function topUp(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $driver = Auth::guard('sanctum')->user();
        $driver->balance += $request->input('amount');
        $driver->save();

        return response()->json([
            'message' => 'Баланс успішно поповнено',
            'balance' => $driver->balance
        ]);
    }

    public function getBalance()
    {
        $driver = Auth::guard('sanctum')->user();

        return response()->json([
            'balance' => $driver->balance
        ]);
    }
}

