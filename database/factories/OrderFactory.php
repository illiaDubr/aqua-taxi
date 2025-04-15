<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'type' => $this->faker->randomElement(['silver', 'deep_clean']),
            'bottles_count' => $this->faker->numberBetween(1, 5),
            'address' => $this->faker->address,
            'lat' => $this->faker->latitude(50.4, 50.5),
            'lng' => $this->faker->longitude(30.4, 30.6),
            'status' => 'pending',
            'scheduled_at' => now()->addHours(rand(1, 24)),
        ];
    }

}
