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
    $validated = $request->validate([
        'email' => 'required|email|exists:drivers,email',
        'password' => 'required|string',
    ]);

    /** @var \App\Models\Driver|null $driver */
    $driver = Driver::where('email', $validated['email'])->first();

    if (!$driver) {
        // На всякий случай: логируем и возвращаем 401
        Log::warning('Driver login failed: not found', ['email' => $validated['email']]);
        return response()->json(['message' => 'Невірні дані'], 401);
    }

    if (!Hash::check($validated['password'], $driver->password)) {
        Log::warning('Driver login failed: bad password', ['driver_id' => $driver->id]);
        throw ValidationException::withMessages([
            'password' => ['Невірний пароль'],
        ]);
    }

    // Убедимся, что модель поддерживает токены (HasApiTokens)
    if (!method_exists($driver, 'createToken')) {
        Log::error('Driver model missing createToken method - check HasApiTokens trait');
        return response()->json(['message' => 'Server error'], 500);
    }

    // Создаём токен и возвращаем
    $token = $driver->createToken('driver-token', ['driver'])->plainTextToken;

    return response()->json([
        'driver' => $driver,
        'token' => $token,
    ], 200);
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
