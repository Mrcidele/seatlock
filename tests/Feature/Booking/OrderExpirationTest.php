<?php

declare(strict_types=1);

use App\Booking\Jobs\ExpireOrderJob;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SeatSegment;
use App\Models\Trip;
use App\Payments\Gateways\FakeGateway;
use App\Payments\PaymentGatewayManager;
use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    $this->user = customer();
});

function seatStatus(Trip $trip, int $origin = 0, int $destination = 3): string
{
    return test()->getJson("/api/v1/trips/{$trip->id}/seat-map?origin={$origin}&destination={$destination}")->json('data.decks.0.cells.0.0.seat.status');
}

it('schedules the expiration job with a delay equal to the ttl', function (): void {
    Queue::fake();

    $order = placeOrder($this->trip, ['01']);

    Queue::assertPushed(ExpireOrderJob::class, fn (ExpireOrderJob $job): bool => $job->orderId === $order['id']
        && $job->delay instanceof DateTimeInterface
        && abs(now()->addMinutes(10)->diffInSeconds($job->delay)) < 2);
});

it('expires the order and releases the seats when the job runs after the deadline', function (): void {
    $order = placeOrder($this->trip, ['01']);
    expect(seatStatus($this->trip))->toBe('locked');

    $this->travel(11)->minutes();
    (new ExpireOrderJob($order['id']))->handle(app(App\Booking\Actions\ExpireOrder::class));

    $expired = Order::query()->findOrFail($order['id']);
    expect($expired->status)->toBe(OrderStatus::Expired)
        ->and($expired->expired_at)->not->toBeNull()
        ->and($expired->reservations()->first()?->status)->toBe(ReservationStatus::Released)
        ->and(app(SeatLockService::class)->owners($this->trip->id, [seatNumbered($this->trip, '01')->id], new Leg(0, 3)))->toBe([]);
});

it('does nothing when the job runs before the (renewed) deadline', function (): void {
    $order = placeOrder($this->trip, ['01']);
    $this->travel(8)->minutes();
    $this->postJson("/api/v1/orders/{$order['id']}/renew")->assertOk();

    $this->travel(3)->minutes();
    (new ExpireOrderJob($order['id']))->handle(app(App\Booking\Actions\ExpireOrder::class));

    expect(Order::query()->findOrFail($order['id'])->status)->toBe(OrderStatus::Pending);
});

it('reconciles stale pending orders whose job was lost', function (): void {
    $stale = Order::factory()->create(['expires_at' => now()->subMinutes(5)]);
    $withinGrace = Order::factory()->create(['expires_at' => now()->subSeconds(5)]);
    $paid = Order::factory()->status(OrderStatus::Paid)->create(['expires_at' => now()->subHour()]);

    $this->artisan('orders:expire-stale')->expectsOutputToContain('1 pedido(s)')->assertSuccessful();

    expect($stale->refresh()->status)->toBe(OrderStatus::Expired)
        ->and($withinGrace->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($paid->refresh()->status)->toBe(OrderStatus::Paid);
});

it('is scheduled every minute', function (): void {
    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'orders:expire-stale'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});

it('reactivates the seats when the payment is approved after expiration', function (): void {
    $order = placeOrder($this->trip, ['01']);
    $paymentId = $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'pix'], ['Idempotency-Key' => 'late-pix-1'])->json('payment_id');

    $this->travel(11)->minutes();
    $this->artisan('orders:expire-stale');
    expect(Order::query()->findOrFail($order['id'])->status)->toBe(OrderStatus::Expired);

    $this->postJson("/api/v1/dev/payments/{$paymentId}/simulate", ['status' => 'approved'])->assertAccepted();

    expect(Order::query()->findOrFail($order['id'])->status)->toBe(OrderStatus::Paid)
        ->and(Payment::query()->findOrFail($paymentId)->status)->toBe(PaymentStatus::Approved)
        ->and(SeatSegment::query()->count())->toBe(3);
});

it('refunds automatically when the seat was sold to someone else after expiration', function (): void {
    $order = placeOrder($this->trip, ['01']);
    $payment = Payment::query()->findOrFail(
        $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'pix'], ['Idempotency-Key' => 'late-pix-2'])->json('payment_id'),
    );

    $this->travel(11)->minutes();
    $this->artisan('orders:expire-stale');

    // Outro cliente compra o mesmo assento depois da expiração.
    Sanctum::actingAs(customer());
    $other = placeOrder($this->trip, ['01'], new Leg(1, 2), cartId: 'cart-bob-0001');
    $this->postJson("/api/v1/orders/{$other['id']}/payments", ['method' => 'card', 'card_token' => 'tok_visa'], ['Idempotency-Key' => 'bob-card'])
        ->assertJsonPath('data.status', 'paid');

    // Chega o Pix atrasado do primeiro cliente.
    $gateway = app(PaymentGatewayManager::class)->gateway('fake');
    assert($gateway instanceof FakeGateway);
    $webhook = $gateway->signedWebhook((string) $payment->external_id, PaymentStatus::Approved);
    $this->call('POST', '/api/v1/webhooks/fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => $webhook['headers'][FakeGateway::SIGNATURE_HEADER],
    ], $webhook['body'])->assertAccepted();

    expect(Order::query()->findOrFail($order['id'])->status)->toBe(OrderStatus::Expired)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_cents)->toBe($payment->amount_cents)
        ->and(SeatSegment::query()->pluck('reservation_id')->unique()->count())->toBe(1);
});
