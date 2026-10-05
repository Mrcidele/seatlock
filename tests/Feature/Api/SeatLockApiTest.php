<?php

declare(strict_types=1);

use App\Enums\SeatType;
use App\Models\Trip;
use App\Models\User;
use App\ValueObjects\Leg;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    $this->seat = seatNumbered($this->trip, '01');
    $this->url = "/api/v1/trips/{$this->trip->id}/locks";
    $this->payload = ['origin' => 0, 'destination' => 2, 'seat_ids' => [$this->seat->id]];
});

it('requires authentication', function (): void {
    $this->postJson($this->url, $this->payload)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('requires a cart id', function (): void {
    customer();

    $this->postJson($this->url, $this->payload)
        ->assertStatus(400)
        ->assertJsonPath('type', 'http://localhost/problems/missing-cart-id');
});

it('locks seats and shows them as locked on the map', function (): void {
    $alice = customer();

    $this->postJson($this->url, $this->payload, cartHeaders())
        ->assertCreated()
        ->assertJsonPath('data.seat_ids', [$this->seat->id])
        ->assertJsonPath('data.degraded', false)
        ->assertJsonStructure(['data' => ['expires_at', 'server_time']]);

    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=2", cartHeaders())
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'locked')
        ->assertJsonPath('data.decks.0.cells.0.0.seat.held_by_you', true);

    // O mesmo assento continua livre num trecho que não usa os segmentos travados.
    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=2&destination=3")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'available');

    customer();
    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=2", cartHeaders('other-cart-123'))
        ->assertJsonPath('data.decks.0.cells.0.0.seat.held_by_you', false);
});

it('rejects a seat locked by someone else and suggests alternatives of the same type', function (): void {
    customer();
    $this->postJson($this->url, $this->payload, cartHeaders())->assertCreated();

    customer();
    $response = $this->postJson($this->url, ['origin' => 1, 'destination' => 3, 'seat_ids' => [$this->seat->id]], cartHeaders('bob-cart-0001'))
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/seat-unavailable')
        ->assertJsonPath('conflicting_seat_ids', [$this->seat->id]);

    $alternatives = $response->json('alternatives');
    expect($alternatives)->not->toBeEmpty()
        ->and($alternatives[0]['type'])->toBe(SeatType::Conventional->value)
        ->and(collect($alternatives)->pluck('id'))->not->toContain($this->seat->id);
});

it('does not lock a seat already sold in the database', function (): void {
    sellSeat($this->trip, $this->seat, new Leg(1, 2));
    customer();

    $this->postJson($this->url, $this->payload, cartHeaders())
        ->assertStatus(409)
        ->assertJsonPath('conflicting_seat_ids', [$this->seat->id]);
});

it('is all or nothing when one of the seats is taken', function (): void {
    customer();
    $this->postJson($this->url, $this->payload, cartHeaders())->assertCreated();

    customer();
    $other = seatNumbered($this->trip, '02');
    $this->postJson($this->url, ['origin' => 0, 'destination' => 2, 'seat_ids' => [$other->id, $this->seat->id]], cartHeaders('bob-cart-0001'))
        ->assertStatus(409);

    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=2")
        ->assertJsonPath('data.decks.0.cells.0.1.seat.status', 'available');
});

it('releases only the caller locks', function (): void {
    $alice = customer();
    $this->postJson($this->url, $this->payload, cartHeaders())->assertCreated();

    customer();
    $this->deleteJson($this->url, $this->payload, cartHeaders())->assertNoContent();
    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=2")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'locked');

    Sanctum::actingAs($alice);
    $this->deleteJson($this->url, $this->payload, cartHeaders())->assertNoContent();
    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=2")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'available');
});

it('rejects seats from another vehicle', function (): void {
    customer();
    $foreign = seatNumbered(Trip::factory()->create(), '01');

    $this->postJson($this->url, ['origin' => 0, 'destination' => 1, 'seat_ids' => [$foreign->id]], cartHeaders())
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['seat_ids']]);
});

it('rate limits the lock endpoint per user', function (): void {
    customer(User::factory()->create());

    foreach (range(1, 30) as $_) {
        $this->deleteJson($this->url, $this->payload, cartHeaders());
    }

    $this->postJson($this->url, $this->payload, cartHeaders())
        ->assertStatus(429)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('Retry-After');
});
