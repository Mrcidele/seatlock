<?php

declare(strict_types=1);

namespace App\Payments\Data;

use App\Enums\PaymentStatus;

final readonly class WebhookNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        /** ID do evento no provedor: base da deduplicação. */
        public string $eventId,
        public string $type,
        public ?string $externalPaymentId,
        /** Null quando o provedor só avisa "mudou" e precisamos consultar. */
        public ?PaymentStatus $status,
        public array $payload,
    ) {}
}
