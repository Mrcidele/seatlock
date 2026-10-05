<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\TravelRoute;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por padrão: linha com 4 paradas (A, B, C, D => 3 segmentos) e veículo
 * compacto de 12 assentos, partindo amanhã.
 *
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => TravelRoute::factory()->withStops(4),
            'vehicle_id' => Vehicle::factory(),
            'departure_at' => now()->addDay()->startOfHour(),
            'status' => TripStatus::Scheduled,
        ];
    }

    public function departingIn(int $hours): static
    {
        return $this->state(fn (): array => ['departure_at' => now()->addHours($hours)]);
    }
}
