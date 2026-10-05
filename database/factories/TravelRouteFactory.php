<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Stop;
use App\Models\TravelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRoute>
 */
class TravelRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('RT-####')),
            'name' => fake()->city().' - '.fake()->city(),
        ];
    }

    /**
     * Cria N paradas com 60 min e R$ 20,00 por segmento.
     */
    public function withStops(int $count = 4, int $farePerSegmentCents = 2_000): static
    {
        return $this->afterCreating(function (TravelRoute $route) use ($count, $farePerSegmentCents): void {
            foreach (range(0, $count - 1) as $sequence) {
                Stop::query()->create([
                    'route_id' => $route->id,
                    'sequence' => $sequence,
                    'name' => 'Rodoviária '.chr(65 + $sequence),
                    'city' => 'Cidade '.chr(65 + $sequence),
                    'state' => 'SP',
                    'minutes_from_origin' => $sequence * 60,
                    'fare_from_origin_cents' => $sequence * $farePerSegmentCents,
                ]);
            }
        });
    }
}
