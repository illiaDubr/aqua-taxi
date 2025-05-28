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
        $data = $request->validate([
            'email' => 'required|email|unique:drivers,email',
            'phone' => 'required|string|unique:drivers,phone',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
        ]);

        $driver = Driver::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        return response()->json(['message' => 'Успешная регистрация', 'driver' => $driver]);
    }
}

