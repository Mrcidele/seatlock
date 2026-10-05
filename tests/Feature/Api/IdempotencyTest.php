<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Trip;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    customer();
    $this->seat = seatNumbered($this->trip, '01');
    $this->postJson("/api/v1/trips/{$this->trip->id}/locks", ['origin' => 0, 'destination' => 3, 'seat_ids' => [$this->seat->id]], cartHeaders())->assertCreated();
    $this->headers = [...cartHeaders(), 'Idempotency-Key' => 'double-click-0001'];
});

it('returns the same order when the request is repeated with the same key', function (): void {
    $first = $this->postJson('/api/v1/orders', orderPayload($this->trip, [$this->seat->id]), $this->headers)->assertCreated();
    $second = $this->postJson('/api/v1/orders', orderPayload($this->trip, [$this->seat->id]), $this->headers)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($second->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and(Order::query()->count())->toBe(1);
});

it('rejects the same key with a different payload', function (): void {
    $this->postJson('/api/v1/orders', orderPayload($this->trip, [$this->seat->id]), $this->headers)->assertCreated();

    $payload = orderPayload($this->trip, [$this->seat->id]);
    $payload['passengers'][0]['name'] = 'Outra Pessoa';

    $this->postJson('/api/v1/orders', $payload, $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('type', 'http://localhost/problems/idempotency-key-reused');
});

it('requires the idempotency key header', function (): void {
    $this->postJson('/api/v1/orders', orderPayload($this->trip, [$this->seat->id]), cartHeaders())
        ->assertStatus(400)
        ->assertJsonPath('type', 'http://localhost/problems/idempotency-key-required');
});

it('stores client errors so a retry gets the same answer', function (): void {
    $payload = orderPayload($this->trip, [$this->seat->id]);
    $payload['origin'] = 5;

    $this->postJson('/api/v1/orders', $payload, $this->headers)->assertStatus(422);
    $this->postJson('/api/v1/orders', $payload, $this->headers)
        ->assertStatus(422)
        ->assertHeader('Idempotent-Replayed', 'true');
});

it('scopes keys per user', function (): void {
    $this->postJson('/api/v1/orders', orderPayload($this->trip, [$this->seat->id]), $this->headers)->assertCreated();

    customer();
    // Outro usuário com a mesma chave não recebe o pedido do primeiro.
    $this->postJson('/api/v1/orders', orderPayload($this->trip, [$this->seat->id]), $this->headers)
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/lock-not-held');
});
