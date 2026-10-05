<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Passenger;
use App\Models\Reservation;
use App\Models\Seat;
use App\Models\SeatSegment;
use App\Models\Trip;
use App\Models\User;
use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Registra uma venda confirmada diretamente no banco (fixture).
 */
function sellSeat(Trip $trip, Seat $seat, Leg $leg): Reservation
{
    $order = Order::factory()->for($trip)->create([
        'origin_index' => $leg->origin,
        'destination_index' => $leg->destination,
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
    ]);

    $reservation = Reservation::query()->create([
        'order_id' => $order->id,
        'trip_id' => $trip->id,
        'seat_id' => $seat->id,
        'passenger_id' => Passenger::factory()->create()->id,
        'origin_index' => $leg->origin,
        'destination_index' => $leg->destination,
        'price_cents' => 1_000,
        'currency' => 'BRL',
        'status' => ReservationStatus::Confirmed,
    ]);

    SeatSegment::query()->insert($reservation->segmentRows());

    return $reservation;
}

function seatNumbered(Trip $trip, string $number): Seat
{
    return Seat::query()->where('vehicle_id', $trip->vehicle_id)->where('number', $number)->firstOrFail();
}

/**
 * Aponta a conexão de locks para uma porta fechada, simulando o Redis fora do ar.
 */
function simulateRedisOutage(): void
{
    Redis::purge('locks');
    config()->set('database.redis.locks.port', 1);
    // O RedisManager guarda a configuração ao ser criado; recria-o.
    app()->forgetInstance('redis');
    Redis::clearResolvedInstance('redis');
    app()->forgetInstance(SeatLockService::class);
}

function customer(?User $user = null): User
{
    $user ??= User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

/**
 * @return array<string, string>
 */
function cartHeaders(string $cartId = 'cart-0000-aaaa'): array
{
    return ['X-Cart-Id' => $cartId];
}

/**
 * Fluxo completo via API: trava os assentos e cria o pedido.
 *
 * @param  list<string>  $seatNumbers
 * @return array<string, mixed> JSON do pedido criado
 */
function placeOrder(Trip $trip, array $seatNumbers, ?Leg $leg = null, string $cartId = 'cart-0000-aaaa', ?string $idempotencyKey = null): array
{
    $leg ??= new Leg(0, 3);
    $seatIds = array_map(fn (string $n): string => seatNumbered($trip, $n)->id, $seatNumbers);
    $test = test();

    $test->postJson("/api/v1/trips/{$trip->id}/locks", [
        'origin' => $leg->origin, 'destination' => $leg->destination, 'seat_ids' => $seatIds,
    ], cartHeaders($cartId))->assertCreated();

    return $test->postJson('/api/v1/orders', orderPayload($trip, $seatIds, $leg), [
        ...cartHeaders($cartId),
        'Idempotency-Key' => $idempotencyKey ?? (string) Illuminate\Support\Str::uuid(),
    ])->assertCreated()->json('data');
}

/**
 * @param  list<string>  $seatIds
 * @return array<string, mixed>
 */
function orderPayload(Trip $trip, array $seatIds, ?Leg $leg = null): array
{
    $leg ??= new Leg(0, 3);

    return [
        'trip_id' => $trip->id,
        'origin' => $leg->origin,
        'destination' => $leg->destination,
        'passengers' => array_map(fn (string $seatId): array => [
            'seat_id' => $seatId,
            'name' => 'Passageiro '.substr($seatId, -4),
            'document' => '123.456.789-09',
        ], $seatIds),
    ];
}
