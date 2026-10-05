<?php

declare(strict_types=1);

use App\Models\Trip;
use App\ValueObjects\Leg;

it('returns the seat map ready to render', function (): void {
    $trip = Trip::factory()->create();

    $response = $this->getJson("/api/v1/trips/{$trip->id}/seat-map?origin=0&destination=3")
        ->assertOk()
        ->assertJsonPath('data.trip_id', $trip->id)
        ->assertJsonPath('data.leg.segments', [0, 1, 2])
        ->assertJsonPath('data.decks.0.rows', 3)
        ->assertJsonPath('data.decks.0.columns', 5)
        ->assertJsonPath('data.decks.0.cells.0.2.kind', 'aisle')
        ->assertJsonPath('data.decks.0.cells.0.0.seat.number', '01')
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'available')
        ->assertJsonPath('data.decks.0.cells.0.0.seat.price.cents', 6_000)
        ->assertJsonPath('data.decks.0.cells.2.0.seat.type', 'sleeper')
        ->assertJsonPath('data.decks.0.cells.2.0.seat.price.cents', 9_000)
        ->assertJsonPath('data.summary', ['available' => 12, 'locked' => 0, 'sold' => 0]);

    expect($response->json('data.decks.0.cells.2.1.seat.type'))->toBe('accessible');
});

it('marks seats sold on any segment of the requested leg', function (): void {
    $trip = Trip::factory()->create();
    sellSeat($trip, seatNumbered($trip, '01'), new Leg(1, 2));

    $this->getJson("/api/v1/trips/{$trip->id}/seat-map?origin=0&destination=2")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'sold')
        ->assertJsonPath('data.summary.sold', 1);

    $this->getJson("/api/v1/trips/{$trip->id}/seat-map?origin=2&destination=3")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'available');
});

it('validates the leg with problem details', function (): void {
    $trip = Trip::factory()->create();

    $this->getJson("/api/v1/trips/{$trip->id}/seat-map?origin=2&destination=9")
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 422)
        ->assertJsonStructure(['type', 'title', 'status', 'errors' => ['destination']]);
});

it('returns 404 problem for unknown trips', function (): void {
    $this->getJson('/api/v1/trips/01JZZZZZZZZZZZZZZZZZZZZZZZ/seat-map?origin=0&destination=1')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});
