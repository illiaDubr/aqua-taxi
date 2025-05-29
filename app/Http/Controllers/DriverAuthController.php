<?php


namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DriverAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:drivers,email',
            'phone' => 'required|string|unique:drivers,phone',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
        ]);

        try {
            $driver = Driver::create([
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'balance' => 0.00,
            ]);

            $token = $driver->createToken('driver-token')->plainTextToken;

            return response()->json([
                'driver' => $driver,
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Регистрация водителя ошибка', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'message' => 'Помилка сервера при реєстрації водія',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:drivers,email',
            'password' => 'required|string',
        ]);

        $driver = Driver::where('email', $validated['email'])->first();

        if (!Hash::check($validated['password'], $driver->password)) {
            throw ValidationException::withMessages([
                'password' => ['Невірний пароль'],
            ]);
        }

        $token = $driver->createToken('driver-token')->plainTextToken;

        return response()->json([
            'driver' => $driver,
            'token' => $token,
            'balance' => $driver->balance,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Вихід виконано успішно']);
    }
}
