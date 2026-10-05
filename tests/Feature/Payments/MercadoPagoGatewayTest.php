<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Data\ChargeRequest;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentGatewayError;
use App\Payments\Gateways\MercadoPagoGateway;
use App\ValueObjects\Money;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function mercadoPago(): MercadoPagoGateway
{
    return new MercadoPagoGateway(app(Factory::class), 'https://api.mercadopago.com', 'TEST-token', 'mp-secret');
}

function chargeRequest(PaymentMethod $method, ?string $token = null): ChargeRequest
{
    return new ChargeRequest('01PAYMENT', '01ORDER', Money::of(12_345), $method, 'ana@example.com', 'Passagem', now()->addMinutes(10), $token, 'visa');
}

it('creates a pix payment with an idempotency key and maps the qr code', function (): void {
    Http::fake(['api.mercadopago.com/v1/payments' => Http::response([
        'id' => 123456,
        'status' => 'pending',
        'payment_method_id' => 'pix',
        'date_of_expiration' => '2030-01-01T10:00:00.000-03:00',
        'point_of_interaction' => ['transaction_data' => ['qr_code' => '00020126...6304ABCD']],
    ], 201)]);

    $result = mercadoPago()->charge(chargeRequest(PaymentMethod::Pix));

    expect($result->externalId)->toBe('123456')
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->pixCopyPaste)->toBe('00020126...6304ABCD');

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('X-Idempotency-Key', '01PAYMENT')
        && $request->hasHeader('Authorization', 'Bearer TEST-token')
        && $request['transaction_amount'] === 123.45
        && $request['payment_method_id'] === 'pix'
        && $request['external_reference'] === '01ORDER');
});

it('maps card statuses', function (string $status, PaymentStatus $expected): void {
    Http::fake(['*' => Http::response(['id' => 1, 'status' => $status, 'status_detail' => 'cc_rejected_other_reason', 'payment_method_id' => 'visa', 'card' => ['last_four_digits' => '1111']])]);

    $result = mercadoPago()->charge(chargeRequest(PaymentMethod::Card, 'card-token'));

    expect($result->status)->toBe($expected)->and($result->cardLastFour)->toBe('1111');
})->with([
    ['approved', PaymentStatus::Approved],
    ['in_process', PaymentStatus::Pending],
    ['rejected', PaymentStatus::Declined],
    ['refunded', PaymentStatus::Refunded],
]);

it('wraps http failures', function (): void {
    Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

    mercadoPago()->charge(chargeRequest(PaymentMethod::Pix));
})->throws(PaymentGatewayError::class);

it('validates the x-signature header of webhooks', function (): void {
    $ts = '1704908010';
    $manifest = "id:123456;request-id:req-1;ts:{$ts};";
    $signature = hash_hmac('sha256', $manifest, 'mp-secret');

    $request = Request::create('/api/v1/webhooks/mercadopago?data.id=123456&type=payment', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => "ts={$ts},v1={$signature}",
        'HTTP_X_REQUEST_ID' => 'req-1',
    ], content: (string) json_encode(['id' => 999, 'action' => 'payment.updated', 'data' => ['id' => '123456']]));

    $notification = mercadoPago()->parseWebhook($request);

    expect($notification->eventId)->toBe('999')
        ->and($notification->externalPaymentId)->toBe('123456')
        ->and($notification->status)->toBeNull();

    $request->headers->set('x-signature', "ts={$ts},v1=forged");
    mercadoPago()->parseWebhook($request);
})->throws(InvalidWebhookSignature::class);
