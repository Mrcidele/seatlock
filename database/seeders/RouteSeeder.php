<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Stop;
use App\Models\TravelRoute;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    public function run(): void
    {
        $routes = [
            'SP-UDI' => [
                'name' => 'São Paulo - Uberlândia',
                'stops' => [
                    ['Terminal Tietê', 'São Paulo', 'SP', 0, 0],
                    ['Rodoviária de Campinas', 'Campinas', 'SP', 100, 4_500],
                    ['Rodoviária de Ribeirão Preto', 'Ribeirão Preto', 'SP', 280, 11_000],
                    ['Rodoviária de Uberaba', 'Uberaba', 'MG', 400, 15_500],
                    ['Rodoviária de Uberlândia', 'Uberlândia', 'MG', 500, 19_000],
                ],
            ],
            'RJ-SP' => [
                'name' => 'Rio de Janeiro - São Paulo',
                'stops' => [
                    ['Rodoviária Novo Rio', 'Rio de Janeiro', 'RJ', 0, 0],
                    ['Rodoviária de Resende', 'Resende', 'RJ', 150, 6_000],
                    ['Rodoviária de São José dos Campos', 'São José dos Campos', 'SP', 300, 10_500],
                    ['Terminal Tietê', 'São Paulo', 'SP', 390, 13_000],
                ],
            ],
        ];

        foreach ($routes as $code => $data) {
            $route = TravelRoute::query()->updateOrCreate(['code' => $code], ['name' => $data['name']]);

            foreach ($data['stops'] as $sequence => [$name, $city, $state, $minutes, $fare]) {
                Stop::query()->updateOrCreate(
                    ['route_id' => $route->id, 'sequence' => $sequence],
                    ['name' => $name, 'city' => $city, 'state' => $state, 'minutes_from_origin' => $minutes, 'fare_from_origin_cents' => $fare],
                );
            }
        }
    }
}
