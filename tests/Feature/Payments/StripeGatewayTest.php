<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Data\ChargeRequest;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Gateways\StripeGateway;
use App\ValueObjects\Money;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function stripe(): StripeGateway
{
    return new StripeGateway(app(Factory::class), 'https://api.stripe.com', 'sk_test_x', 'whsec_test');
}

function stripeCharge(): ChargeRequest
{
    return new ChargeRequest('01PAYMENT', '01ORDER', Money::of(9_990), PaymentMethod::Card, 'ana@example.com', 'Passagem', now()->addMinutes(10), 'pm_card_visa');
}

function stripeWebhook(array $event, ?int $timestamp = null, string $secret = 'whsec_test'): Request
{
    $body = (string) json_encode($event);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return Request::create('/api/v1/webhooks/stripe', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: $body);
}

it('only supports cards', function (): void {
    expect(stripe()->supports(PaymentMethod::Card))->toBeTrue()
        ->and(stripe()->supports(PaymentMethod::Pix))->toBeFalse();
});

it('confirms a payment intent with the client token and integer cents', function (): void {
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response([
        'id' => 'pi_123', 'status' => 'succeeded',
        'payment_method' => ['card' => ['brand' => 'visa', 'last4' => '4242']],
    ])]);

    $result = stripe()->charge(stripeCharge());

    expect($result->status)->toBe(PaymentStatus::Approved)
        ->and($result->externalId)->toBe('pi_123')
        ->and($result->cardLastFour)->toBe('4242');

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Idempotency-Key', '01PAYMENT')
        && $request['amount'] === 9990
        && $request['currency'] === 'brl'
        && $request['payment_method'] === 'pm_card_visa');
});

it('treats a 402 card error as a declined payment', function (): void {
    Http::fake(['*' => Http::response(['error' => [
        'code' => 'card_declined',
        'payment_intent' => ['id' => 'pi_9', 'status' => 'requires_payment_method', 'last_payment_error' => ['code' => 'card_declined']],
    ]], 402)]);

    $result = stripe()->charge(stripeCharge());

    expect($result->status)->toBe(PaymentStatus::Declined)
        ->and($result->failureReason)->toBe('card_declined');
});

it('parses signed webhook events', function (): void {
    $notification = stripe()->parseWebhook(stripeWebhook([
        'id' => 'evt_1', 'type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_1', 'payment_intent' => 'pi_123']],
    ]));

    expect($notification->eventId)->toBe('evt_1')
        ->and($notification->externalPaymentId)->toBe('pi_123')
        ->and($notification->status)->toBe(PaymentStatus::Refunded);
});

it('rejects webhooks signed with another secret', function (): void {
    stripe()->parseWebhook(stripeWebhook(['id' => 'evt_1', 'type' => 'payment_intent.succeeded'], secret: 'whsec_other'));
})->throws(InvalidWebhookSignature::class);

it('rejects replayed webhooks outside the tolerance window', function (): void {
    stripe()->parseWebhook(stripeWebhook(['id' => 'evt_1', 'type' => 'payment_intent.succeeded'], timestamp: time() - 3_600));
})->throws(InvalidWebhookSignature::class);
