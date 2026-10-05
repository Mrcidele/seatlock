<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $order_id
 * @property string $gateway
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property int $amount_cents
 * @property int $refunded_cents
 * @property string $currency
 * @property Money $amount
 * @property Money $refunded
 * @property string|null $external_id
 * @property string|null $pix_copy_paste
 * @property CarbonImmutable|null $pix_expires_at
 * @property string|null $card_brand
 * @property string|null $card_last_four
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Order $order
 */
#[Fillable([
    'order_id', 'gateway', 'method', 'status', 'amount_cents', 'refunded_cents', 'currency', 'external_id',
    'pix_copy_paste', 'pix_expires_at', 'card_brand', 'card_last_four', 'failure_reason', 'approved_at', 'refunded_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'refunded_cents' => 'integer',
            'amount' => MoneyCast::class.':amount_cents,currency',
            'refunded' => MoneyCast::class.':refunded_cents,currency',
            'pix_expires_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
