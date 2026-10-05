<?php

declare(strict_types=1);

use App\Models\Trip;
use Database\Seeders\FleetSeeder;
use Database\Seeders\RouteSeeder;
use Database\Seeders\TripSeeder;

beforeEach(function (): void {
    $this->seed([FleetSeeder::class, RouteSeeder::class, TripSeeder::class]);
});

it('finds trips that pass through origin before destination', function (): void {
    $date = now()->addDay()->format('Y-m-d');

    $response = $this->getJson("/api/v1/trips?origin=campinas&destination=Uberaba&date={$date}")->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('data.0.leg'))->toBe(['origin' => 1, 'destination' => 3])
        ->and($response->json('data.0.from_price.cents'))->toBe(11_000)
        ->and($response->json('data.0.available_seats'))->toBeGreaterThan(0);
});

it('ignores trips going the opposite direction', function (): void {
    $this->getJson('/api/v1/trips?origin=Uberaba&destination=Campinas')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows a trip with its stops', function (): void {
    $trip = Trip::query()->firstOrFail();

    $this->getJson("/api/v1/trips/{$trip->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $trip->id)
        ->assertJsonCount($trip->stopCount(), 'data.stops');
});
