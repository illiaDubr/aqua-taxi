<?php

namespace App\Http\Controllers;

use App\Models\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FactoryAuthController extends Controller
{
    /**
     * Регистрация производителя + загрузка сертификата.
     * Требует: email, phone, password, website, warehouse_address, water_types(JSON string), certificate(file)
     */
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

        // ---- 2) Загрузка сертификата на диск 'public' ----
        // В БД храним путь (например: "certificates/xxx.pdf")
        // Публичный URL для фронта строим через Storage::url(...)
        $certificatePath = $request->file('certificate')->store('certificates', 'public');

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
                    'User-Agent' => 'AquaTaxi/1.0 (contact: support@example.com)',
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
            'email'                 => $request->email,
            'phone'                 => $request->phone,
            'password'              => Hash::make($request->password),
            'website'               => $request->website,
            'warehouse_address'     => $request->warehouse_address,
            'water_types'           => $clean,        // МАССИВ (в модели Factory должен быть casts: ['water_types' => 'array'])
            'certificate_path'      => $certificatePath, // сохраняем ПУТЬ
            'certificate_status'    => 'pending',
            'certificate_expiration'=> null,
            'is_verified'           => false,
            'verified_until'        => null,
            'lat'                   => $lat,
            'lng'                   => $lng,
        ]);

        // Готовим расширенный ответ с абсолютным URL сертификата
        $response = $this->factoryResource($factory);

        return response()->json([
            'message' => 'Реєстрація успішна. Сертифікат відправлений на модерацію.',
            'factory' => $response,
        ], 201);
    }

    /**
     * Логин производителя (Sanctum token с ability "factory").
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        $factory = Factory::where('email', $credentials['email'])->first();

        if (!$factory || !Hash::check($credentials['password'], $factory->password)) {
            return response()->json(['message' => 'Невірні дані'], 401);
        }

        // по желанию: сбрасываем все старые токены
        $factory->tokens()->delete();

        // abilities для фабрики
        $token = $factory->createToken('factory', ['factory'])->plainTextToken;

        return response()->json([
            'token'   => $token,
            'user'    => $this->factoryResource($factory),
            'factory' => $this->factoryResource($factory),
        ]);
    }

    public function logout(Request $request)
{
    $request->user()->tokens()->delete();
    return response()->json(['message' => 'Вихід виконано успішно']);
}
    /**
     * Повторная загрузка/замена сертификата.
     */
    public function uploadCertificate(Request $request)
    {
        /** @var Factory $factory */
        $factory = auth()->user();

        $request->validate([
            'certificate' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $path = $request->file('certificate')->store('certificates', 'public');

        // сохраняем ПУТЬ; URL строим динамически через Storage::url(...)
        $factory->update([
            'certificate_path'       => $path,
            'certificate_status'     => 'pending',
            'certificate_expiration' => null,
        ]);

        return response()->json([
            'message' => 'Сертифікат успішно завантажено та відправлено на перевірку.',
            'factory' => $this->factoryResource($factory->fresh()),
        ], 200);
    }

    /**
     * Приватный "ресурс" для фабрики: добавляет абсолютный certificate_url.
     */
    private function factoryResource(Factory $factory): array
    {
        $factory = $factory->toArray();

        // Абсолютный URL к файлу сертификата (если путь есть)
        $factory['certificate_url'] = null;
        if (!empty($factory['certificate_path'])) {
            // Storage::url('certificates/xxx.pdf') => "/storage/certificates/xxx.pdf"
            // url(...) сделает абсолютный "http(s)://domain/storage/..."
            $factory['certificate_url'] = url(Storage::url($factory['certificate_path']));
        }

        return $factory;
    }
}
