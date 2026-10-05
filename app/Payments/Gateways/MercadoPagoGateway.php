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
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Mercado Pago (API v1/payments): Pix e cartão tokenizado pelo SDK JS.
 *
 * O webhook do Mercado Pago só informa o ID do pagamento; o status é sempre
 * consultado na API (fetch), o que também neutraliza eventos fora de ordem.
 */
final readonly class MercadoPagoGateway implements PaymentGateway
{
    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $accessToken,
        private string $webhookSecret,
        private ?string $notificationUrl = null,
    ) {}

    public function name(): string
    {
        return 'mercadopago';
    }

    public function supports(PaymentMethod $method): bool
    {
        return true;
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        $body = array_filter([
            // A API exige o valor em reais (decimal); convertemos só na borda.
            'transaction_amount' => round($request->amount->cents / 100, 2),
            'description' => $request->description,
            'external_reference' => $request->orderId,
            'notification_url' => $this->notificationUrl,
            'payer' => ['email' => $request->payerEmail],
        ], fn (mixed $value): bool => $value !== null);

        if ($request->method === PaymentMethod::Pix) {
            $body['payment_method_id'] = 'pix';
            $body['date_of_expiration'] = $request->expiresAt->format('Y-m-d\TH:i:s.vP');
        } else {
            $body['token'] = $request->cardToken;
            $body['installments'] = 1;
            $body['payment_method_id'] = $request->cardBrand;
        }

        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeaders(['X-Idempotency-Key' => $request->paymentId])
            ->post('/v1/payments', $body));

        return $this->toResult($response->json());
    }

    public function fetch(string $externalId): ChargeResult
    {
        return $this->toResult($this->send(fn (PendingRequest $http): Response => $http->get("/v1/payments/{$externalId}"))->json());
    }

    public function refund(Payment $payment, Money $amount): RefundResult
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeaders(['X-Idempotency-Key' => "refund-{$payment->id}-{$amount->cents}"])
            ->post("/v1/payments/{$payment->external_id}/refunds", [
                'amount' => round($amount->cents / 100, 2),
            ]));

        return new RefundResult((string) $this->scalar($response->json('id')), $amount->cents);
    }

    /**
     * x-signature: "ts=<ts>,v1=<hmac>" e o HMAC-SHA256 é calculado sobre
     * "id:<data.id>;request-id:<x-request-id>;ts:<ts>;".
     */
    public function parseWebhook(Request $request): WebhookNotification
    {
        $parts = [];
        foreach (explode(',', (string) $request->header('x-signature', '')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        $dataId = $request->query('data_id', $request->input('data.id'));
        $dataId = is_scalar($dataId) ? strtolower((string) $dataId) : '';
        $requestId = (string) $request->header('x-request-id', '');
        $ts = $parts['ts'] ?? '';

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $this->webhookSecret);

        if ($dataId === '' || $ts === '' || ! hash_equals($expected, $parts['v1'] ?? '')) {
            throw new InvalidWebhookSignature('Assinatura do Mercado Pago não confere.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $eventId = $this->scalar($payload['id'] ?? null) ?? "{$dataId}:{$ts}";
        $type = $this->scalar($payload['action'] ?? $payload['type'] ?? null) ?? 'payment';

        return new WebhookNotification((string) $eventId, (string) $type, $dataId, null, $payload);
    }

    /**
     * @param  mixed  $data  corpo JSON de um pagamento
     */
    private function toResult(mixed $data): ChargeResult
    {
        if (! is_array($data) || $this->scalar($data['id'] ?? null) === null) {
            throw new PaymentGatewayError('Resposta inválida do Mercado Pago.');
        }

        $status = match ($data['status'] ?? null) {
            'approved' => PaymentStatus::Approved,
            'rejected' => PaymentStatus::Declined,
            'cancelled' => PaymentStatus::Cancelled,
            'refunded', 'charged_back' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending, // pending, in_process, authorized, in_mediation
        };

        $qr = Arr::get($data, 'point_of_interaction.transaction_data.qr_code');
        $expiration = $data['date_of_expiration'] ?? null;
        $lastFour = Arr::get($data, 'card.last_four_digits');
        $refunded = $data['transaction_amount_refunded'] ?? 0;

        return new ChargeResult(
            externalId: (string) $this->scalar($data['id']),
            status: $status,
            pixCopyPaste: is_string($qr) ? $qr : null,
            pixExpiresAt: is_string($expiration) ? CarbonImmutable::parse($expiration) : null,
            cardBrand: is_string($data['payment_method_id'] ?? null) && $data['payment_method_id'] !== 'pix' ? $data['payment_method_id'] : null,
            cardLastFour: is_string($lastFour) ? $lastFour : null,
            failureReason: $status === PaymentStatus::Declined && is_string($data['status_detail'] ?? null) ? $data['status_detail'] : null,
            refundedCents: is_numeric($refunded) ? (int) round((float) $refunded * 100) : 0,
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call($this->http->baseUrl($this->baseUrl)->withToken($this->accessToken)->acceptJson()->timeout(10));
        } catch (ConnectionException $e) {
            throw new PaymentGatewayError($e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new PaymentGatewayError("Mercado Pago respondeu HTTP {$response->status()}.");
        }

        return $response;
    }

    private function scalar(mixed $value): string|int|null
    {
        return is_string($value) || is_int($value) ? $value : null;
    }
}
