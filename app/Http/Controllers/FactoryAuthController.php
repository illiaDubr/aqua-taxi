<?php

namespace App\Http\Controllers;

use App\Models\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
            'certificate' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240', // добавил pdf + увеличил лимит
        ]);

        // 📥 Сохраняем сертификат
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
            'certificate_path' => str_replace('public/', 'storage/', $certificatePath), // сохраняем в поле certificate_file
            'certificate_status' => 'pending', // выставляем статус сертификата
            'certificate_expiration' => null,
            'is_verified' => false,
            'verified_until' => null,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        return response()->json(['message' => 'Реєстрація успішна. Сертифікат відправлений на модерацію.', 'factory' => $factory], 201);
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
    public function uploadCertificate(Request $request)
    {
        $factory = auth()->user(); // получаем текущего производителя

        $request->validate([
            'certificate' => 'required|file|mimes:jpeg,png,pdf|max:10240', // 10 MB
        ]);

        // сохраняем файл
        $path = $request->file('certificate')->store('public/certificates');

        // обновляем фабрику
        $factory->update([
            'certificate_file' => str_replace('public/', 'storage/', $path),
            'certificate_status' => 'pending',
            'certificate_expiration' => null,
        ]);

        return response()->json(['message' => 'Сертификат успешно загружен и отправлен на проверку.'], 200);
    }
}
