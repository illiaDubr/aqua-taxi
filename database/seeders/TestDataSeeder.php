<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
use Illuminate\Database\Seeder;



class TestDataSeeder extends Seeder
{
    public function run()
    {
        // Клиенты
        $clients = User::factory(5)->create();

        // Водители
        $drivers = User::factory(5)->driver()->create();

        // Заказы: по 2 на клиента
        foreach ($clients as $client) {
            Order::factory()->count(2)->create([
                'user_id' => $client->id,
            ]);
        }

        // Назначим часть заказов водителям и изменим статус
        $orders = Order::where('status', 'pending')->get();
        foreach ($orders->take(5) as $i => $order) {
            $driver = $drivers[$i % $drivers->count()];
            $order->update([
                'driver_id' => $driver->id,
                'status' => 'accepted',
            ]);
        }

        foreach ($orders->slice(5)->take(2) as $order) {
            $driver = $drivers->random();
            $order->update([
                'driver_id' => $driver->id,
                'status' => 'delivered',
            ]);
        }
    }
}


