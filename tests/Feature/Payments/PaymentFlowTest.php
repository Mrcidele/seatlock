<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SeatSegment;
use App\Models\Trip;
use App\Payments\PixBrCode;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    $this->user = customer();
    $this->order = placeOrder($this->trip, ['01']);
});

function pay(string $orderId, array $body, ?string $key = null): Illuminate\Testing\TestResponse
{
    return test()->postJson("/api/v1/orders/{$orderId}/payments", $body, ['Idempotency-Key' => $key ?? (string) Illuminate\Support\Str::uuid()]);
}

it('pays with pix and confirms when the signed webhook arrives', function (): void {
    $response = pay($this->order['id'], ['method' => 'pix'])->assertCreated();

    $pix = $response->json('data.payments.0.pix.copy_paste');
    expect($response->json('data.status'))->toBe('pending')
        ->and($response->json('data.payments.0.status'))->toBe('pending')
        ->and(substr($pix, -4))->toBe(PixBrCode::crc16(substr($pix, 0, -4)));

    $this->postJson('/api/v1/dev/payments/'.$response->json('payment_id').'/simulate', ['status' => 'approved'])->assertAccepted();

    $this->getJson("/api/v1/orders/{$this->order['id']}")
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.payments.0.status', 'approved');

    expect(SeatSegment::query()->count())->toBe(3);
});

it('returns the same pix charge while it is pending', function (): void {
    $first = pay($this->order['id'], ['method' => 'pix'])->json('payment_id');
    $second = pay($this->order['id'], ['method' => 'pix'])->json('payment_id');

    expect($second)->toBe($first)->and(Payment::query()->count())->toBe(1);
});

it('confirms immediately when the card is approved', function (): void {
    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_visa', 'card_brand' => 'visa'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.payments.0.card.last_four', '4242');
});

it('keeps the order pending when the card is declined so the customer can retry', function (): void {
    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_declined'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payments.0.status', 'declined');

    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_visa'])->assertJsonPath('data.status', 'paid');
});

it('reports gateway failures as 502 problems', function (): void {
    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_error'])
        ->assertStatus(502)
        ->assertJsonPath('type', 'http://localhost/problems/payment-gateway-error');

    expect(Payment::query()->first()?->failure_reason)->toBe('gateway_error');
});

it('never accepts raw card data', function (): void {
    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_visa', 'card_number' => '4242424242424242', 'cvv' => '123'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['card_number', 'cvv']]);
});

it('refuses to charge an expired order', function (): void {
    $this->travel(11)->minutes();

    pay($this->order['id'], ['method' => 'pix'])
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/order-expired');
});

it('does not create two charges for a double click on pay', function (): void {
    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_visa'], 'pay-click-0001')->assertCreated();
    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_visa'], 'pay-click-0001')
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect(Payment::query()->count())->toBe(1);
});

it('refunds a second approved payment for an order that is already paid', function (): void {
    $pix = pay($this->order['id'], ['method' => 'pix'])->json('payment_id');
    pay($this->order['id'], ['method' => 'card', 'card_token' => 'tok_visa'])->assertJsonPath('data.status', 'paid');

    // O cliente também pagou o Pix que tinha gerado antes.
    $this->postJson("/api/v1/dev/payments/{$pix}/simulate", ['status' => 'approved'])->assertAccepted();

    expect(Payment::query()->findOrFail($pix)->status)->toBe(PaymentStatus::Refunded)
        ->and(Payment::query()->where('status', PaymentStatus::Approved)->count())->toBe(1)
        ->and(Order::query()->findOrFail($this->order['id'])->status)->toBe(OrderStatus::Paid);
});
