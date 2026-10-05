<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\WebhookEventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $provider
 * @property string $event_id
 * @property string $type
 * @property string|null $external_payment_id
 * @property PaymentStatus|null $reported_status
 * @property array<string, mixed> $payload
 * @property WebhookEventStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['provider', 'event_id', 'type', 'external_payment_id', 'reported_status', 'payload', 'status', 'attempts', 'error', 'processed_at'])]
class WebhookEvent extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookEventStatus::class,
            'reported_status' => PaymentStatus::class,
            'attempts' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
