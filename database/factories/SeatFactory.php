<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeatType;
use App\Models\Seat;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'number' => str_pad((string) fake()->unique()->numberBetween(50, 99), 2, '0', STR_PAD_LEFT),
            'deck' => 1,
            'row' => fake()->numberBetween(20, 40),
            'column' => fake()->numberBetween(0, 4),
            'type' => SeatType::Conventional,
        ];
    }
}
