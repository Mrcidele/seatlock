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
use App\Payments\PixBrCode;
use App\ValueObjects\Money;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gateway de desenvolvimento e testes. Imita o comportamento de um provedor
 * real: Pix fica pendente até chegar um webhook assinado; cartão aprova ou
 * recusa conforme o token ("tok_declined" recusa, "tok_error" simula queda).
 */
final readonly class FakeGateway implements PaymentGateway
{
    public const string SIGNATURE_HEADER = 'X-Fake-Signature';

    public function __construct(
        private Cache $cache,
        private string $webhookSecret,
        private string $pixKey,
        private string $merchantName,
        private string $merchantCity,
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function supports(PaymentMethod $method): bool
    {
        return true;
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        $externalId = 'fake_'.Str::lower((string) Str::ulid());

        $result = match ($request->method) {
            PaymentMethod::Pix => new ChargeResult(
                externalId: $externalId,
                status: PaymentStatus::Pending,
                pixCopyPaste: PixBrCode::make($this->pixKey, $request->amount, $this->merchantName, $this->merchantCity, $request->paymentId),
                pixExpiresAt: $request->expiresAt,
            ),
            PaymentMethod::Card => $this->chargeCard($externalId, $request),
        };

        $this->remember($result);

        return $result;
    }

    public function fetch(string $externalId): ChargeResult
    {
        $stored = $this->cache->get($this->key($externalId));

        if (! $stored instanceof ChargeResult) {
            throw new PaymentGatewayError("Pagamento {$externalId} desconhecido no gateway fake.");
        }

        return $stored;
    }

    public function refund(Payment $payment, Money $amount): RefundResult
    {
        if ($payment->external_id !== null && $this->cache->has($this->key($payment->external_id))) {
            $current = $this->fetch($payment->external_id);
            $this->remember(new ChargeResult(
                externalId: $current->externalId,
                status: PaymentStatus::Refunded,
                refundedCents: $current->refundedCents + $amount->cents,
            ));
        }

        return new RefundResult('fake_refund_'.Str::lower((string) Str::ulid()), $amount->cents);
    }

    public function parseWebhook(Request $request): WebhookNotification
    {
        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $this->webhookSecret);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignature('Assinatura do webhook fake não confere.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $status = is_string($payload['status'] ?? null) ? PaymentStatus::tryFrom($payload['status']) : null;
        $eventId = $payload['id'] ?? null;
        $paymentId = $payload['payment_id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            throw new InvalidWebhookSignature('Webhook sem ID de evento.');
        }

        return new WebhookNotification(
            eventId: $eventId,
            type: is_string($payload['type'] ?? null) ? $payload['type'] : 'payment.updated',
            externalPaymentId: is_string($paymentId) ? $paymentId : null,
            status: $status,
            payload: $payload,
        );
    }

    /**
     * Monta um webhook assinado como o provedor enviaria. Usado pelo endpoint
     * de simulação em ambiente local e pelos testes.
     *
     * @return array{body: string, headers: array<string, string>}
     */
    public function signedWebhook(string $externalId, PaymentStatus $status, ?string $eventId = null): array
    {
        if ($status === PaymentStatus::Approved && $this->cache->has($this->key($externalId))) {
            $this->remember(new ChargeResult($externalId, PaymentStatus::Approved));
        }

        $body = (string) json_encode([
            'id' => $eventId ?? 'evt_'.Str::lower((string) Str::ulid()),
            'type' => 'payment.updated',
            'payment_id' => $externalId,
            'status' => $status->value,
        ]);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                self::SIGNATURE_HEADER => 'sha256='.hash_hmac('sha256', $body, $this->webhookSecret),
            ],
        ];
    }

    private function chargeCard(string $externalId, ChargeRequest $request): ChargeResult
    {
        $token = (string) $request->cardToken;

        if ($token === 'tok_error') {
            throw new PaymentGatewayError('Falha simulada no gateway fake.');
        }

        $approved = $token !== 'tok_declined';

        return new ChargeResult(
            externalId: $externalId,
            status: $approved ? PaymentStatus::Approved : PaymentStatus::Declined,
            cardBrand: $request->cardBrand ?? 'visa',
            cardLastFour: '4242',
            failureReason: $approved ? null : 'card_declined',
        );
    }

    private function remember(ChargeResult $result): void
    {
        $this->cache->put($this->key($result->externalId), $result, now()->addDay());
    }

    private function key(string $externalId): string
    {
        return "fake-gateway:{$externalId}";
    }
}
