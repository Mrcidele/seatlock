<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Stop;
use App\Models\TravelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stop>
 */
class StopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => TravelRoute::factory(),
            'sequence' => 0,
            'name' => 'Rodoviária de '.fake()->city(),
            'city' => fake()->city(),
            'state' => 'SP',
            'minutes_from_origin' => 0,
            'fare_from_origin_cents' => 0,
        ];
    }
}
