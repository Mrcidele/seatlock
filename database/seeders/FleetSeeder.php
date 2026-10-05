<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Layout\GenerateSeatsFromLayout;
use App\Layout\LayoutPresets;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Cria os veículos e gera os assentos a partir do layout JSON de cada um.
 */
class FleetSeeder extends Seeder
{
    public function run(GenerateSeatsFromLayout $generator): void
    {
        $vehicles = [
            ['plate' => 'SLK1A23', 'name' => 'Double Decker 01', 'layout' => LayoutPresets::doubleDecker()],
            ['plate' => 'SLK2B34', 'name' => 'Convencional 02', 'layout' => LayoutPresets::conventional()],
        ];

        foreach ($vehicles as $data) {
            $vehicle = Vehicle::query()->updateOrCreate(['plate' => $data['plate']], $data);

            if (! $vehicle->seats()->exists()) {
                $generator->handle($vehicle);
            }
        }
    }
}
