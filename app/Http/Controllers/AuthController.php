<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    public function requestCode(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        $code = rand(1000, 9999);
        DB::table('phone_codes')->updateOrInsert(
            ['phone' => $request->phone],
            [
                'code' => $code,
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // В реальности здесь будет отправка SMS
        return response()->json(['message' => 'Code sent', 'code' => $code]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string',
        ]);

        $row = DB::table('phone_codes')
            ->where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Invalid or expired code'], 401);
        }

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            ['is_verified' => true]
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
    public function topUp(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $driver = $request->user();

        $driver->balance += $validated['amount'];
        $driver->save();

        return response()->json([
            'message' => 'Баланс успішно поповнено',
            'new_balance' => $driver->balance,
        ]);
    }
}
