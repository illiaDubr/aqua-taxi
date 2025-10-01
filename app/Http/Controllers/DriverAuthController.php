<?php

namespace App\Http\Controllers;

use App\Models\Driver; // твоя модель водителя
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class DriverAuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email','unique:drivers,email'],
            'password' => ['required','string','min:6'],
            'phone'    => ['nullable','string','max:30'],
            'name'     => ['nullable','string','max:100'],
            'surname'  => ['nullable','string','max:255'], // если колонка NOT NULL — сделай required
        ]);

        $driver = Driver::create([
            'email'    => trim($data['email']),
            'password' => Hash::make($data['password']),
            'phone'    => $data['phone']   ?? null,
            'name'     => $data['name']    ?? null,
            'surname'  => $data['surname'] ?? null, // ← добавлено
        ]);

        $token = $driver->createToken('driver_token', ['driver'])->plainTextToken;

        return response()->json([
            'token'  => $token,
            'driver' => $driver,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        /** @var Driver|null $driver */
        $driver = Driver::where('email', $data['email'])->first();

        if (!$driver || !Hash::check($data['password'], $driver->password)) {
            return response()->json(['message' => 'Невірні дані'], 401);
        }

        // ВАЖНО: ability = driver
        $token = $driver->createToken('driver_token', ['driver'])->plainTextToken;

        return response()->json([
            'token'  => $token,
            'driver' => $driver,
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        // убережёмся от “чужого” токена → 403, а не 500
        if (!$user || !$request->user()->tokenCan('driver')) {
            return response()->json(['message' => 'Only drivers'], 403);
        }

        // безопасный ответ (если нет колонок — вернём 0)
        return response()->json([
            'id'      => $user->id,
            'email'   => $user->email,
            'name'    => $user->name ?? null,
            'phone'   => $user->phone ?? null,
            'balance' => $user->balance ?? 0,
            'bottles' => $user->bottles ?? 0,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'OK']);
    }
}
