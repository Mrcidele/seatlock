<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SeatSegment;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\ValueObjects\Leg;
use Laravel\Sanctum\Sanctum;

function paidOrder(Trip $trip, string $seat = '01'): array
{
    $order = placeOrder($trip, [$seat], new Leg(0, 3), 'cart-'.$seat.'-'.substr($trip->id, -6));
    test()->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'card_token' => 'tok_visa'], ['Idempotency-Key' => 'pay-'.$order['id']])
        ->assertJsonPath('data.status', 'paid');

    return $order;
}

beforeEach(function (): void {
    customer();
});

it('refunds according to how far in advance the customer cancels', function (int $hoursBefore, int $expectedCents): void {
    $trip = Trip::factory()->departingIn($hoursBefore)->create();
    $order = paidOrder($trip); // R$ 60,00

    $this->postJson("/api/v1/orders/{$order['id']}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.refunded.cents', $expectedCents);

    $payment = Payment::query()->where('order_id', $order['id'])->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_cents)->toBe($expectedCents)
        ->and(SeatSegment::query()->count())->toBe(0)
        ->and(Ticket::query()->whereNull('revoked_at')->count())->toBe(0);
})->with([
    '72h+ => 100%' => [100, 6_000],
    '24h+ => 95%' => [30, 5_700],
    '3h+ => 80%' => [5, 4_800],
]);

it('does not cancel within 3 hours of departure', function (): void {
    $order = paidOrder(Trip::factory()->departingIn(2)->create());

    $this->postJson("/api/v1/orders/{$order['id']}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/cancellation-window-closed');

    expect(Order::query()->findOrFail($order['id'])->status)->toBe(OrderStatus::Paid);
});

it('frees the seat after cancellation so someone else can buy it', function (): void {
    $trip = Trip::factory()->departingIn(100)->create();
    $order = paidOrder($trip);
    $this->postJson("/api/v1/orders/{$order['id']}/cancel")->assertOk();

    Sanctum::actingAs(User::factory()->create());
    paidOrder($trip);

    expect(SeatSegment::query()->count())->toBe(3);
});

it('rebooks to another trip of the same route and reissues the ticket', function (): void {
    $trip = Trip::factory()->departingIn(48)->create();
    $order = paidOrder($trip);
    $oldTicket = Ticket::query()->firstOrFail();
    $newTrip = Trip::factory()->departingIn(72)->create(['route_id' => $trip->route_id, 'vehicle_id' => $trip->vehicle_id]);

    $this->postJson("/api/v1/orders/{$order['id']}/rebook", [
        'trip_id' => $newTrip->id,
        'seats' => [['reservation_id' => $order['reservations'][0]['id'], 'seat_id' => seatNumbered($newTrip, '05')->id]],
    ])->assertOk()->assertJsonPath('data.trip_id', $newTrip->id)->assertJsonPath('data.reservations.0.seat.number', '05');

    expect(SeatSegment::query()->where('trip_id', $trip->id)->count())->toBe(0)
        ->and(SeatSegment::query()->where('trip_id', $newTrip->id)->count())->toBe(3)
        ->and(Ticket::query()->find($oldTicket->id))->toBeNull()
        ->and(Ticket::query()->count())->toBe(1);
});

it('refuses to rebook onto a seat that is already sold', function (): void {
    $trip = Trip::factory()->departingIn(48)->create();
    $newTrip = Trip::factory()->departingIn(72)->create(['route_id' => $trip->route_id, 'vehicle_id' => $trip->vehicle_id]);
    sellSeat($newTrip, seatNumbered($newTrip, '05'), new Leg(1, 2));
    $order = paidOrder($trip);

    $this->postJson("/api/v1/orders/{$order['id']}/rebook", [
        'trip_id' => $newTrip->id,
        'seats' => [['reservation_id' => $order['reservations'][0]['id'], 'seat_id' => seatNumbered($newTrip, '05')->id]],
    ])->assertStatus(409)->assertJsonPath('type', 'http://localhost/problems/seat-unavailable');

    // Nada mudou: o assento original continua vendido na viagem original.
    expect(SeatSegment::query()->where('trip_id', $trip->id)->count())->toBe(3)
        ->and(Order::query()->findOrFail($order['id'])->trip_id)->toBe($trip->id);
});

it('refuses rebooking to another route or too close to departure', function (): void {
    $trip = Trip::factory()->departingIn(2)->create();
    $order = paidOrder($trip);
    $other = Trip::factory()->departingIn(72)->create(['route_id' => $trip->route_id, 'vehicle_id' => $trip->vehicle_id]);

    $this->postJson("/api/v1/orders/{$order['id']}/rebook", [
        'trip_id' => $other->id,
        'seats' => [['reservation_id' => $order['reservations'][0]['id'], 'seat_id' => seatNumbered($other, '05')->id]],
    ])->assertStatus(409)->assertJsonPath('type', 'http://localhost/problems/rebooking-window-closed');
});
