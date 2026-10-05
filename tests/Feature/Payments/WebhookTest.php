<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEventStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SeatSegment;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\WebhookEvent;
use App\Payments\Gateways\FakeGateway;
use App\Payments\Jobs\ProcessWebhookEvent;
use App\Payments\PaymentGatewayManager;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    customer();
    $this->order = placeOrder($this->trip, ['01']);
    $paymentId = $this->postJson("/api/v1/orders/{$this->order['id']}/payments", ['method' => 'pix'], ['Idempotency-Key' => 'pix-key-0001'])->json('payment_id');
    $this->payment = Payment::query()->findOrFail($paymentId);
    $this->gateway = app(PaymentGatewayManager::class)->gateway('fake');
});

function sendWebhook(FakeGateway $gateway, string $externalId, PaymentStatus $status, ?string $eventId = null): Illuminate\Testing\TestResponse
{
    $webhook = $gateway->signedWebhook($externalId, $status, $eventId);

    return test()->call('POST', '/api/v1/webhooks/fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => $webhook['headers'][FakeGateway::SIGNATURE_HEADER],
    ], $webhook['body']);
}

it('rejects webhooks with an invalid signature', function (): void {
    $this->call('POST', '/api/v1/webhooks/fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => 'sha256=forged',
    ], (string) json_encode(['id' => 'evt_1', 'payment_id' => $this->payment->external_id, 'status' => 'approved']))
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('returns 404 for unknown providers', function (): void {
    $this->postJson('/api/v1/webhooks/acme', [])->assertNotFound();
});

it('processes a duplicated webhook only once', function (): void {
    sendWebhook($this->gateway, (string) $this->payment->external_id, PaymentStatus::Approved, 'evt_dup')
        ->assertAccepted()->assertJsonPath('status', 'accepted');
    sendWebhook($this->gateway, (string) $this->payment->external_id, PaymentStatus::Approved, 'evt_dup')
        ->assertOk()->assertJsonPath('status', 'duplicate');

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and(Ticket::query()->count())->toBe(1)
        ->and(SeatSegment::query()->count())->toBe(3)
        ->and(Order::query()->findOrFail($this->order['id'])->status)->toBe(OrderStatus::Paid);
});

it('ignores a stale pending webhook that arrives after the approval', function (): void {
    sendWebhook($this->gateway, (string) $this->payment->external_id, PaymentStatus::Approved, 'evt_2');
    sendWebhook($this->gateway, (string) $this->payment->external_id, PaymentStatus::Pending, 'evt_1');

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and(WebhookEvent::query()->where('event_id', 'evt_1')->value('status'))->toBe(WebhookEventStatus::Ignored)
        ->and(Order::query()->findOrFail($this->order['id'])->status)->toBe(OrderStatus::Paid);
});

it('retries a webhook that arrives before the payment is stored', function (): void {
    $this->payment->update(['external_id' => null]);

    sendWebhook($this->gateway, 'fake_not_stored_yet', PaymentStatus::Approved, 'evt_early')->assertAccepted();

    $event = WebhookEvent::query()->where('event_id', 'evt_early')->firstOrFail();
    expect($event->status)->toBe(WebhookEventStatus::Received)
        ->and($event->attempts)->toBe(1);

    // A resposta da cobrança é gravada; a retentativa do job encontra o pagamento.
    $this->payment->update(['external_id' => 'fake_not_stored_yet']);
    ProcessWebhookEvent::dispatchSync($event->id);

    expect($event->refresh()->status)->toBe(WebhookEventStatus::Processed)
        ->and(Order::query()->findOrFail($this->order['id'])->status)->toBe(OrderStatus::Paid);
});

it('undoes the sale when the provider reports a refund', function (): void {
    sendWebhook($this->gateway, (string) $this->payment->external_id, PaymentStatus::Approved);
    sendWebhook($this->gateway, (string) $this->payment->external_id, PaymentStatus::Refunded);

    $order = Order::query()->findOrFail($this->order['id']);

    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and(SeatSegment::query()->count())->toBe(0)
        ->and(Ticket::query()->whereNull('revoked_at')->count())->toBe(0);
});

it('ignores a refund notification for a payment that was never approved', function (): void {
    sendWebhook($this->gateway, (string) $this->payment->external_id, PaymentStatus::Refunded);

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Pending);
});
