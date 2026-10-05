<?php

declare(strict_types=1);

namespace App\Payments\Data;

final readonly class RefundResult
{
    public function __construct(
        public string $externalRefundId,
        public int $refundedCents,
    ) {}
}
