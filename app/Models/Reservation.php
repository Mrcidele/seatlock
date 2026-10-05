<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ReservationStatus;
use App\ValueObjects\Leg;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $order_id
 * @property string $trip_id
 * @property string $seat_id
 * @property string $passenger_id
 * @property int $origin_index
 * @property int $destination_index
 * @property int $price_cents
 * @property string $currency
 * @property Money $price
 * @property ReservationStatus $status
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Order $order
 * @property-read Trip $trip
 * @property-read Seat $seat
 * @property-read Passenger $passenger
 * @property-read Ticket|null $ticket
 */
#[Fillable([
    'order_id', 'trip_id', 'seat_id', 'passenger_id', 'origin_index', 'destination_index',
    'price_cents', 'currency', 'status',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
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
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * @return BelongsTo<Passenger, $this>
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    /**
     * @return HasMany<SeatSegment, $this>
     */
    public function seatSegments(): HasMany
    {
        return $this->hasMany(SeatSegment::class);
    }

    /**
     * @return HasOne<Ticket, $this>
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    public function leg(): Leg
    {
        return new Leg($this->origin_index, $this->destination_index);
    }

    /**
     * Linhas que esta reserva ocupa em seat_segments quando confirmada.
     *
     * @return list<array{trip_id: string, seat_id: string, segment_index: int, reservation_id: string}>
     */
    public function segmentRows(): array
    {
        return array_map(fn (int $segment): array => [
            'trip_id' => $this->trip_id,
            'seat_id' => $this->seat_id,
            'segment_index' => $segment,
            'reservation_id' => $this->id,
        ], $this->leg()->segments());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin_index' => 'integer',
            'destination_index' => 'integer',
            'price_cents' => 'integer',
            'price' => MoneyCast::class.':price_cents,currency',
            'status' => ReservationStatus::class,
        ];
    }
}
