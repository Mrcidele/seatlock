<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Passenger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Passenger>
 */
class PassengerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'document' => fake()->numerify('###########'),
            'email' => fake()->safeEmail(),
            'phone' => null,
        ];
    }
}
