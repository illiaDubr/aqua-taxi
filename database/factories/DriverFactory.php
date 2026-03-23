<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->firstName,
            'surname' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->unique()->numerify('+380#########'),
            'password' => bcrypt('password'),
            'balance' => $this->faker->randomFloat(2, 0, 1000),
            'bottles' => $this->faker->numberBetween(0, 50),
            'api_token' => $this->faker->sha256,
            'telegram_id' => null,
            'last_lat' => null,
            'last_lng' => null,
        ];
    }
}
