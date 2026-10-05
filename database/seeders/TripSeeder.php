<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TripStatus;
use App\Models\TravelRoute;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = Vehicle::query()->orderBy('plate')->get();
        $start = CarbonImmutable::today();

        foreach (TravelRoute::query()->orderBy('code')->get() as $routeIndex => $route) {
            foreach (range(0, 6) as $day) {
                foreach (['08:00', '14:30', '22:00'] as $slot => $time) {
                    $vehicle = $vehicles[($routeIndex + $slot) % $vehicles->count()];
                    $departure = CarbonImmutable::parse($start->addDays($day)->format('Y-m-d').' '.$time);

                    Trip::query()->firstOrCreate(
                        ['route_id' => $route->id, 'departure_at' => $departure],
                        ['vehicle_id' => $vehicle->id, 'status' => TripStatus::Scheduled],
                    );
                }
            }
        }
    }
}
