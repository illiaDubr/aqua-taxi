<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'phone' => $this->faker->unique()->numerify('+380#########'),
            'role' => 'client',
            'is_verified' => true,
            'is_available' => null,
            'lat' => null,
            'lng' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function driver()
    {
        return $this->state(function () {
            return [
                'role' => 'driver',
                'is_available' => true,
                'lat' => $this->faker->latitude(50.4, 50.5),
                'lng' => $this->faker->longitude(30.4, 30.6),
            ];
        });
    }
}
