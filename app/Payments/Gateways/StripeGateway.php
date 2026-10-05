<?php

declare(strict_types=1);

namespace App\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Data\ChargeRequest;
use App\Payments\Data\ChargeResult;
use App\Payments\Data\RefundResult;
use App\Payments\Data\WebhookNotification;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentGatewayError;
use App\Payments\PaymentGateway;
use App\ValueObjects\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Stripe (PaymentIntents) para cartão. O cliente cria o PaymentMethod com
 * Stripe.js/Elements e envia só o ID (pm_...).
 */
final readonly class StripeGateway implements PaymentGateway
{
    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $secret,
        private string $webhookSecret,
        private int $toleranceSeconds = 300,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function supports(PaymentMethod $method): bool
    {
        return $method === PaymentMethod::Card;
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeaders(['Idempotency-Key' => $request->paymentId])
            ->asForm()
            ->post('/v1/payment_intents', [
                'amount' => $request->amount->cents,
                'currency' => strtolower($request->amount->currency),
                'payment_method' => $request->cardToken,
                'payment_method_types' => ['card'],
                'confirm' => 'true',
                'description' => $request->description,
                'receipt_email' => $request->payerEmail,
                'metadata' => ['order_id' => $request->orderId, 'payment_id' => $request->paymentId],
                'expand' => ['payment_method'],
            ]));

        return $this->toResult($response->json());
    }

    public function fetch(string $externalId): ChargeResult
    {
        return $this->toResult($this->send(fn (PendingRequest $http): Response => $http
            ->get("/v1/payment_intents/{$externalId}", ['expand' => ['payment_method']]))->json());
    }

    public function refund(Payment $payment, Money $amount): RefundResult
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeaders(['Idempotency-Key' => "refund-{$payment->id}-{$amount->cents}"])
            ->asForm()
            ->post('/v1/refunds', [
                'payment_intent' => $payment->external_id,
                'amount' => $amount->cents,
            ]));

        $id = $response->json('id');

        return new RefundResult(is_string($id) ? $id : '', $amount->cents);
    }

    /**
     * Stripe-Signature: "t=<ts>,v1=<hmac>"; HMAC-SHA256 de "<ts>.<corpo>".
     */
    public function parseWebhook(Request $request): WebhookNotification
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $request->header('Stripe-Signature', '')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! is_numeric($timestamp) || abs(time() - (int) $timestamp) > $this->toleranceSeconds) {
            throw new InvalidWebhookSignature('Timestamp do webhook Stripe ausente ou fora da tolerância.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $this->webhookSecret);
        $valid = array_filter($signatures, fn (string $signature): bool => hash_equals($expected, $signature));

        if ($valid === []) {
            throw new InvalidWebhookSignature('Assinatura do Stripe não confere.');
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();
        $type = is_string($event['type'] ?? null) ? $event['type'] : '';
        $object = Arr::get($event, 'data.object');
        $object = is_array($object) ? $object : [];

        $externalId = str_starts_with($type, 'charge.') ? ($object['payment_intent'] ?? null) : ($object['id'] ?? null);

        $status = match ($type) {
            'payment_intent.succeeded' => PaymentStatus::Approved,
            'payment_intent.payment_failed' => PaymentStatus::Declined,
            'payment_intent.canceled' => PaymentStatus::Cancelled,
            'charge.refunded' => PaymentStatus::Refunded,
            default => null,
        };

        $eventId = $event['id'] ?? null;

        if (! is_string($eventId)) {
            throw new InvalidWebhookSignature('Evento Stripe sem ID.');
        }

        return new WebhookNotification($eventId, $type, is_string($externalId) ? $externalId : null, $status, $event);
    }

    private function toResult(mixed $intent): ChargeResult
    {
        if (! is_array($intent) || ! is_string($intent['id'] ?? null)) {
            throw new PaymentGatewayError('Resposta inválida do Stripe.');
        }

        $status = match ($intent['status'] ?? null) {
            'succeeded' => PaymentStatus::Approved,
            'canceled' => PaymentStatus::Cancelled,
            'requires_payment_method' => PaymentStatus::Declined,
            default => PaymentStatus::Pending, // processing, requires_action, requires_confirmation
        };

        $brand = Arr::get($intent, 'payment_method.card.brand');
        $last4 = Arr::get($intent, 'payment_method.card.last4');
        $error = Arr::get($intent, 'last_payment_error.code');
        $secret = $intent['client_secret'] ?? null;

        return new ChargeResult(
            externalId: $intent['id'],
            status: $status,
            cardBrand: is_string($brand) ? $brand : null,
            cardLastFour: is_string($last4) ? $last4 : null,
            failureReason: $status === PaymentStatus::Declined && is_string($error) ? $error : null,
            clientSecret: $status === PaymentStatus::Pending && is_string($secret) ? $secret : null,
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call($this->http->baseUrl($this->baseUrl)->withToken($this->secret)->acceptJson()->timeout(10));
        } catch (ConnectionException $e) {
            throw new PaymentGatewayError($e->getMessage(), previous: $e);
        }

        // 402 = cartão recusado: ainda é um PaymentIntent válido no corpo.
        if ($response->status() === 402 && is_array($response->json('error.payment_intent'))) {
            return new Response(new \GuzzleHttp\Psr7\Response(200, [], (string) json_encode($response->json('error.payment_intent'))));
        }

        if ($response->failed()) {
            throw new PaymentGatewayError("Stripe respondeu HTTP {$response->status()}.");
        }

        return $response;
    }
}
