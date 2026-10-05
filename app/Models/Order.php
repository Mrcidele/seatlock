<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\ValueObjects\Leg;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $user_id
 * @property string $trip_id
 * @property int $origin_index
 * @property int $destination_index
 * @property OrderStatus $status
 * @property int $total_cents
 * @property int $refunded_cents
 * @property string $currency
 * @property Money $total
 * @property Money $refunded
 * @property string $lock_owner
 * @property int $lock_renewals
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $refunded_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read Trip $trip
 * @property-read Collection<int, Reservation> $reservations
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable([
    'user_id', 'trip_id', 'origin_index', 'destination_index', 'status', 'total_cents', 'refunded_cents',
    'currency', 'lock_owner', 'lock_renewals', 'expires_at', 'cancellation_reason',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function leg(): Leg
    {
        return new Leg($this->origin_index, $this->destination_index);
    }

    /**
     * @return list<string>
     */
    public function seatIds(): array
    {
        return array_values($this->reservations->map(fn (Reservation $r): string => $r->seat_id)->all());
    }

    public function isExpiredAt(CarbonImmutable $moment): bool
    {
        return $this->status === OrderStatus::Pending && $this->expires_at->lessThanOrEqualTo($moment);
    }

    /**
     * Única porta de entrada para mudar o status. Transições não previstas na
     * máquina de estados lançam exceção.
     *
     * @throws InvalidOrderTransition
     */
    public function transitionTo(OrderStatus $to, ?string $reason = null): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidOrderTransition::between($this, $this->status, $to);
        }

        $now = CarbonImmutable::now();
        $this->status = $to;

        match ($to) {
            OrderStatus::Paid => $this->paid_at = $now,
            OrderStatus::Expired => $this->expired_at = $now,
            OrderStatus::Cancelled => $this->cancelled_at = $now,
            OrderStatus::Refunded => $this->refunded_at = $now,
            OrderStatus::Pending => null,
        };

        if ($reason !== null) {
            $this->cancellation_reason = $reason;
        }

        $this->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin_index' => 'integer',
            'destination_index' => 'integer',
            'status' => OrderStatus::class,
            'total_cents' => 'integer',
            'refunded_cents' => 'integer',
            'total' => MoneyCast::class.':total_cents,currency',
            'refunded' => MoneyCast::class.':refunded_cents,currency',
            'lock_renewals' => 'integer',
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
