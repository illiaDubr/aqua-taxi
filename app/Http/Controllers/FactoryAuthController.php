<?php

namespace App\Http\Controllers;

use App\Models\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FactoryAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'email'             => 'required|email|unique:factories,email',
            'phone'             => 'required|string',
            'password'          => 'required|string|min:6',
            'website'           => 'required|string',
            'warehouse_address' => 'required|string',
            // приходит строкой (FormData), валидируем как строку, но дальше парсим JSON
            'water_types'       => 'required|string',
            'certificate'       => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'lat'               => 'nullable|numeric',
            'lng'               => 'nullable|numeric',
        ]);

        // ---- 1) Парсим и валидируем water_types JSON ----
        $types = json_decode($request->string('water_types'), true);
        if (!is_array($types)) {
            return response()->json([
                'message' => 'Поле water_types має бути JSON-масивом'
            ], 422);
        }

        // нормализуем [{code,name,price}]
        $clean = [];
        $seen  = [];
        foreach ($types as $t) {
            $code  = strtolower(preg_replace('/[^a-z0-9\-]/', '-', (string)($t['code'] ?? '')));
            $name  = trim((string)($t['name'] ?? $code));
            $price = (float)($t['price'] ?? 0);
            if ($code === '' || $price < 0) {
                return response()->json(['message' => 'Невірні дані типів води'], 422);
            }
            if (isset($seen[$code])) {
                return response()->json(['message' => 'Код типу води має бути унікальним'], 422);
            }
            $seen[$code] = true;
            $clean[] = ['code' => $code, 'name' => $name, 'price' => round($price, 2)];
        }
        if (!count($clean)) {
            return response()->json(['message' => 'Додайте хоча б один тип води'], 422);
        }

        // ---- 2) Загрузка сертификата ----
        $certificatePath = $request->file('certificate')->store('certificates', 'public');
        $certificateUrl  = str_replace('public/', 'storage/', $certificatePath);

        // ---- 3) Координаты ----
        $lat = null;
        $lng = null;

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float)$request->lat;
            $lng = (float)$request->lng;
        } else {
            // Пробуем геокодировать через Nominatim
            try {
                $geoResponse = Http::timeout(5)->withHeaders([
                    'User-Agent' => 'AquaTaxi/1.0 (contact: support@aquataxi.example)', // уважение к Nominatim
                ])->get('https://nominatim.openstreetmap.org/search', [
                    'q'      => $request->warehouse_address,
                    'format' => 'json',
                    'limit'  => 1,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Nominatim timeout/error', ['e' => $e->getMessage()]);
                return response()->json([
                    'error'   => 'geocoding_failed',
                    'message' => 'Не вдалося визначити координати. Встановіть точку вручну.',
                ], 422);
            }

            if ($geoResponse->ok() && count($geoResponse->json() ?? []) > 0) {
                $lat = (float)($geoResponse->json()[0]['lat'] ?? null);
                $lng = (float)($geoResponse->json()[0]['lon'] ?? null);
            } else {
                Log::warning('Nominatim не знайшов координати', [
                    'address'  => $request->warehouse_address,
                    'response' => $geoResponse->json()
                ]);
                return response()->json([
                    'error'   => 'geocoding_failed',
                    'message' => 'Не вдалося визначити координати. Встановіть точку вручну.',
                ], 422);
            }
        }

        // ---- 4) Создание фабрики ----
        $factory = Factory::create([
            'email'                => $request->email,
            'phone'                => $request->phone,
            'password'             => Hash::make($request->password),
            'website'              => $request->website,
            'warehouse_address'    => $request->warehouse_address,
            'water_types'          => $clean, // МАССИВ (модель Factory должна иметь casts)
            'certificate_path'     => $certificateUrl,
            'certificate_status'   => 'pending',
            'certificate_expiration'=> null,
            'is_verified'          => false,
            'verified_until'       => null,
            'lat'                  => $lat,
            'lng'                  => $lng,
        ]);

        return response()->json([
            'message' => 'Реєстрація успішна. Сертифікат відправлений на модерацію.',
            'factory' => $factory,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        $factory = \App\Models\Factory::where('email', $credentials['email'])->first();

        if (!$factory || !\Illuminate\Support\Facades\Hash::check($credentials['password'], $factory->password)) {
            return response()->json(['message' => 'Невірні дані'], 401);
        }

        // по желанию: сбрасываем все старые токены
        $factory->tokens()->delete();

        // ВАЖНО: abilities для фабрики
        $token = $factory->createToken('factory', ['factory'])->plainTextToken;

        return response()->json([
            'token'   => $token,
            'user'    => $factory,
            'factory' => $factory,
        ]);
    }

    public function uploadCertificate(Request $request)
    {
        $factory = auth()->user();

        $request->validate([
            'certificate' => 'required|file|mimes:jpeg,png,pdf|max:10240',
        ]);

        $path = $request->file('certificate')->store('certificates', 'public');
        $url  = str_replace('public/', 'storage/', $path);

        // используем одно поле certificate_path, как в register
        $factory->update([
            'certificate_path'      => $url,
            'certificate_status'    => 'pending',
            'certificate_expiration'=> null,
        ]);

        return response()->json(['message' => 'Сертифікат успішно завантажено та відправлено на перевірку.'], 200);
    }
}
