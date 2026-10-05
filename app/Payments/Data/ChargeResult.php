<?php

declare(strict_types=1);

namespace App\Payments\Data;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

final readonly class ChargeResult
{
    public function __construct(
        public string $externalId,
        public PaymentStatus $status,
        public ?string $pixCopyPaste = null,
        public ?CarbonImmutable $pixExpiresAt = null,
        public ?string $cardBrand = null,
        public ?string $cardLastFour = null,
        public ?string $failureReason = null,
        /** Para 3DS e afins: o cliente conclui a autenticação com isto. */
        public ?string $clientSecret = null,
        public int $refundedCents = 0,
    ) {}
}
