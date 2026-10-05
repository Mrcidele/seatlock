<?php

declare(strict_types=1);

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\Data\ChargeRequest;
use App\Payments\Data\ChargeResult;
use App\Payments\Data\RefundResult;
use App\Payments\Data\WebhookNotification;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentGatewayError;
use App\ValueObjects\Money;
use Illuminate\Http\Request;

/**
 * Porta de saída para provedores de pagamento. O domínio só conhece esta
 * interface; Mercado Pago, Stripe e o gateway fake são adaptadores.
 *
 * Dados de cartão nunca passam pelo nosso servidor: o cliente tokeniza no
 * SDK do provedor e envia apenas o token.
 */
interface PaymentGateway
{
    public function name(): string;

    public function supports(PaymentMethod $method): bool;

    /**
     * @throws PaymentGatewayError
     */
    public function charge(ChargeRequest $request): ChargeResult;

    /**
     * Consulta o estado atual no provedor (fonte da verdade para webhooks
     * que só trazem o ID).
     *
     * @throws PaymentGatewayError
     */
    public function fetch(string $externalId): ChargeResult;

    /**
     * @throws PaymentGatewayError
     */
    public function refund(Payment $payment, Money $amount): RefundResult;

    /**
     * Valida a assinatura e extrai os dados relevantes do webhook.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): WebhookNotification;
}
