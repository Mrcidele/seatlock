<?php

declare(strict_types=1);

namespace App\Payments\Data;

use App\Enums\PaymentMethod;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

final readonly class ChargeRequest
{
    public function __construct(
        /** Nosso ID do pagamento: vira chave de idempotência no provedor. */
        public string $paymentId,
        public string $orderId,
        public Money $amount,
        public PaymentMethod $method,
        public string $payerEmail,
        public string $description,
        public CarbonImmutable $expiresAt,
        /** Token gerado pelo SDK do provedor no navegador. */
        public ?string $cardToken = null,
        public ?string $cardBrand = null,
    ) {}
}
