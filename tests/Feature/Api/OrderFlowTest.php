<?php

declare(strict_types=1);

use App\Booking\Actions\ConfirmOrder;
use App\Booking\ConfirmationOutcome;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\SeatSegment;
use App\Models\Ticket;
use App\Models\Trip;
use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    $this->user = customer();
});

it('creates a pending order for locked seats with the lock expiry', function (): void {
    $order = placeOrder($this->trip, ['01', '02']);

    expect($order['status'])->toBe('pending')
        ->and($order['total']['cents'])->toBe(12_000)
        ->and($order['reservations'])->toHaveCount(2)
        ->and($order['reservations'][0]['passenger']['document'])->toBe('********909')
        ->and($order['renewals_left'])->toBe(1)
        ->and(abs(now()->addMinutes(10)->diffInSeconds($order['expires_at'])))->toBeLessThan(2);

    expect(SeatSegment::query()->count())->toBe(0);
});

it('refuses to create an order without holding the lock', function (): void {
    $seat = seatNumbered($this->trip, '01');

    $this->postJson('/api/v1/orders', orderPayload($this->trip, [$seat->id]), [...cartHeaders(), 'Idempotency-Key' => 'key-00000001'])
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/lock-not-held');
});

it('refuses an order when the lock expired', function (): void {
    $seat = seatNumbered($this->trip, '01');
    $this->postJson("/api/v1/trips/{$this->trip->id}/locks", ['origin' => 0, 'destination' => 3, 'seat_ids' => [$seat->id]], cartHeaders())->assertCreated();

    app(SeatLockService::class)->release($this->trip->id, [$seat->id], new Leg(0, 3), 'user:'.$this->user->id.':cart:cart-0000-aaaa');

    $this->postJson('/api/v1/orders', orderPayload($this->trip, [$seat->id]), [...cartHeaders(), 'Idempotency-Key' => 'key-00000002'])
        ->assertStatus(409);
});

it('allows only one pending order per cart and trip', function (): void {
    placeOrder($this->trip, ['01']);

    $seat = seatNumbered($this->trip, '02');
    $this->postJson("/api/v1/trips/{$this->trip->id}/locks", ['origin' => 0, 'destination' => 3, 'seat_ids' => [$seat->id]], cartHeaders())->assertCreated();

    $this->postJson('/api/v1/orders', orderPayload($this->trip, [$seat->id]), [...cartHeaders(), 'Idempotency-Key' => 'another-key-1'])
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/order-already-pending');
});

it('confirms the order inside a transaction and sells the seat segments', function (): void {
    $order = placeOrder($this->trip, ['01'], new Leg(1, 3));

    expect(app(ConfirmOrder::class)->handle($order['id']))->toBe(ConfirmationOutcome::Confirmed);

    $paid = Order::query()->findOrFail($order['id']);
    expect($paid->status)->toBe(OrderStatus::Paid)
        ->and($paid->paid_at)->not->toBeNull()
        ->and($paid->reservations()->first()?->status)->toBe(ReservationStatus::Confirmed)
        ->and(SeatSegment::query()->orderBy('segment_index')->pluck('segment_index')->all())->toBe([1, 2])
        ->and(Ticket::query()->count())->toBe(1);

    // O lock não é mais necessário: o assento aparece como vendido (banco).
    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=1&destination=2")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'sold');
    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=1")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'available');

    expect(app(ConfirmOrder::class)->handle($order['id']))->toBe(ConfirmationOutcome::AlreadyPaid)
        ->and(SeatSegment::query()->count())->toBe(2);
});

it('treats a unique violation on confirmation as seat unavailable', function (): void {
    // Com o Redis fora do ar, dois carrinhos conseguem criar pedido para o mesmo assento.
    simulateRedisOutage();

    $first = placeOrder($this->trip, ['01'], cartId: 'cart-alice-01');
    Sanctum::actingAs($bob = customer());
    $second = placeOrder($this->trip, ['01'], cartId: 'cart-bob-0001');

    expect(app(ConfirmOrder::class)->handle($first['id']))->toBe(ConfirmationOutcome::Confirmed)
        ->and(app(ConfirmOrder::class)->handle($second['id']))->toBe(ConfirmationOutcome::SeatsTaken);

    $lost = Order::query()->findOrFail($second['id']);
    expect($lost->status)->toBe(OrderStatus::Cancelled)
        ->and($lost->cancellation_reason)->toBe('seat_unavailable')
        ->and($lost->reservations()->first()?->status)->toBe(ReservationStatus::Released)
        ->and(SeatSegment::query()->count())->toBe(3);
});

it('does not confirm a cancelled order', function (): void {
    $order = placeOrder($this->trip, ['01']);

    $this->postJson("/api/v1/orders/{$order['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect(app(ConfirmOrder::class)->handle($order['id']))->toBe(ConfirmationOutcome::NotConfirmable)
        ->and(SeatSegment::query()->count())->toBe(0);
});

it('releases the seats when a pending order is cancelled', function (): void {
    $order = placeOrder($this->trip, ['01']);

    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=3")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'locked');

    $this->postJson("/api/v1/orders/{$order['id']}/cancel")->assertOk();

    $this->getJson("/api/v1/trips/{$this->trip->id}/seat-map?origin=0&destination=3")
        ->assertJsonPath('data.decks.0.cells.0.0.seat.status', 'available');
});

it('renews the order deadline only once', function (): void {
    $order = placeOrder($this->trip, ['01']);
    $this->travel(5)->minutes();

    $renewed = $this->postJson("/api/v1/orders/{$order['id']}/renew")->assertOk()->json('data');

    expect($renewed['renewals_left'])->toBe(0)
        ->and(Order::query()->findOrFail($order['id'])->expires_at->greaterThan(now()->addMinutes(9)))->toBeTrue();

    $this->postJson("/api/v1/orders/{$order['id']}/renew")
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/renewal-limit-reached');
});

it('hides orders from other users', function (): void {
    $order = placeOrder($this->trip, ['01']);

    customer();

    $this->getJson("/api/v1/orders/{$order['id']}")->assertNotFound();
});
