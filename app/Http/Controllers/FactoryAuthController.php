<?php

namespace App\Http\Controllers;

use App\Models\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class FactoryAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:factories,email',
            'phone' => 'required|string',
            'password' => 'required|string|min:6',
            'website' => 'required|string',
            'warehouse_address' => 'required|string',
            'water_types' => 'nullable|string',
            'certificate' => 'required|file|mimes:pdf',
        ]);

        // 🧾 Сохраняем PDF сертификат
        $certificatePath = $request->file('certificate')->store('certificates', 'public');

        // 🌍 Геокодируем адрес через Nominatim
        $geoResponse = Http::get('https://nominatim.openstreetmap.org/search', [
            'q' => $request->warehouse_address,
            'format' => 'json',
            'limit' => 1
        ]);

        $lat = null;
        $lng = null;

        if ($geoResponse->ok() && count($geoResponse->json()) > 0) {
            $lat = $geoResponse->json()[0]['lat'];
            $lng = $geoResponse->json()[0]['lon'];
        }

        // 🏭 Создаём запись производителя
        $factory = Factory::create([
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'website' => $request->website,
            'warehouse_address' => $request->warehouse_address,
            'water_types' => $request->water_types,
            'certificate_path' => $certificatePath,
            'is_verified' => false,
            'verified_until' => null,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        return response()->json(['message' => 'Реєстрація успішна', 'factory' => $factory], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        $factory = Factory::where('email', $credentials['email'])->first();

        if (!$factory || !Hash::check($credentials['password'], $factory->password)) {
            return response()->json(['message' => 'Невірні дані'], 401);
        }

        $token = $factory->createToken('factory_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'factory' => $factory
        ]);
    }
}
