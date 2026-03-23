<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register_and_login()
    {
        $userData = [
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '+380501234567'
        ];

        $response = $this->postJson('/api/user/register', $userData);
        $response->assertStatus(201)
                ->assertJsonStructure(['user', 'token']);

        $loginResponse = $this->postJson('/api/user/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);
        $loginResponse->assertStatus(200)
                     ->assertJsonStructure(['user', 'token']);
    }

    /** @test */
    public function driver_can_register_and_login()
    {
        $driverData = [
            'name' => 'Test Driver',
            'email' => 'driver@example.com',
            'password' => 'password123',
            'phone' => '+380507654321',
            'surname' => 'Testov'
        ];

        $response = $this->postJson('/api/driver/register', $driverData);
        $response->assertStatus(201)
                ->assertJsonStructure(['driver', 'token']);

        $loginResponse = $this->postJson('/api/driver/login', [
            'email' => 'driver@example.com',
            'password' => 'password123'
        ]);
        $loginResponse->assertStatus(200)
                     ->assertJsonStructure(['driver', 'token']);
    }

    /** @test */
    public function authenticated_user_can_create_order()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $orderData = [
            'address' => 'Test Address 123',
            'quantity' => 2,
            'bottle_option' => 'own',
            'bottle_quality' => 'ideal',
            'delivery_time_type' => 'now',
            'payment_method' => 'cash',
            'delivery_option' => 'home',
            'product_name' => 'Silver Water',
            'water_type' => 'silver'
        ];

        $response = $this->postJson('/api/orders', $orderData, [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure(['message', 'order']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'address' => 'Test Address 123',
            'quantity' => 2,
            'status' => 'new'
        ]);
    }

    /** @test */
    public function driver_can_generate_telegram_link_code()
    {
        $driver = Driver::factory()->create();
        $token = $driver->createToken('test', ['driver'])->plainTextToken;

        $response = $this->postJson('/api/driver/telegram/link', [], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure(['code', 'message']);

        $code = $response->json('code');
        $this->assertTrue(Cache::has("telegram_link_{$code}"));
        $this->assertEquals($driver->id, Cache::get("telegram_link_{$code}"));
    }

    /** @test */
    public function driver_can_update_location()
    {
        $driver = Driver::factory()->create();
        $token = $driver->createToken('test', ['driver'])->plainTextToken;

        $locationData = [
            'lat' => 50.4501,
            'lng' => 30.5234
        ];

        $response = $this->postJson('/api/driver/location', $locationData, [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Location updated']);

        $driver->refresh();
        $this->assertEquals(50.4501, $driver->last_lat);
        $this->assertEquals(30.5234, $driver->last_lng);
    }

    /** @test */
    public function driver_can_get_new_orders_with_distance()
    {
        $driver = Driver::factory()->create([
            'last_lat' => 50.4501,
            'last_lng' => 30.5234
        ]);
        $token = $driver->createToken('test', ['driver'])->plainTextToken;

        // Создаем заказ с координатами
        $user = User::factory()->create();
        Order::create([
            'user_id' => $user->id,
            'address' => 'Test Address',
            'latitude' => 50.4601, // ~1km от водителя
            'longitude' => 30.5334,
            'quantity' => 1,
            'bottle_option' => 'own',
            'delivery_time_type' => 'now',
            'payment_method' => 'cash',
            'delivery_option' => 'home',
            'water_type' => 'silver',
            'total_price' => 260,
            'status' => 'new'
        ]);

        $response = $this->getJson('/api/driver/orders/new', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    '*' => [
                        'id', 'address', 'quantity', 'distance_km'
                    ]
                ]);

        $orders = $response->json();
        $this->assertCount(1, $orders);
        $this->assertArrayHasKey('distance_km', $orders[0]);
        $this->assertGreaterThan(0, $orders[0]['distance_km']);
    }

    /** @test */
    public function driver_can_accept_order()
    {
        $driver = Driver::factory()->create();
        $token = $driver->createToken('test', ['driver'])->plainTextToken;

        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'address' => 'Test Address',
            'quantity' => 1,
            'bottle_option' => 'own',
            'delivery_time_type' => 'now',
            'payment_method' => 'cash',
            'delivery_option' => 'home',
            'water_type' => 'silver',
            'total_price' => 260,
            'status' => 'new'
        ]);

        $response = $this->postJson("/api/driver/orders/{$order->id}/accept", [], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Замовлення прийнято']);

        $order->refresh();
        $this->assertEquals('in_progress', $order->status);
        $this->assertEquals($driver->id, $order->driver_id);
    }

    /** @test */
    public function telegram_attach_webhook_requires_token()
    {
        $response = $this->postJson('/api/webhooks/telegram/attach', [
            'code' => 'ABC123',
            'telegram_id' => '123456789'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function telegram_attach_webhook_with_valid_token()
    {
        $driver = Driver::factory()->create();
        $code = 'ABC123';
        Cache::put("telegram_link_{$code}", $driver->id, now()->addMinutes(10));

        $response = $this->postJson('/api/webhooks/telegram/attach', [
            'code' => $code,
            'telegram_id' => '123456789'
        ], [
            'X-Node-Socket-Token' => config('services.nodesocket.token', 'secret-token')
        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Telegram account linked successfully']);

        $driver->refresh();
        $this->assertEquals('123456789', $driver->telegram_id);
    }
}