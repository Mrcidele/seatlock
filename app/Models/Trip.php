<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TripStatus;
use App\ValueObjects\Leg;
use Carbon\CarbonImmutable;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Viagem: uma linha percorrida por um veículo numa data/hora específica.
 * É dividida em segmentos entre paradas consecutivas.
 *
 * @property string $id
 * @property string $route_id
 * @property string $vehicle_id
 * @property CarbonImmutable $departure_at
 * @property TripStatus $status
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read TravelRoute $route
 * @property-read Vehicle $vehicle
 * @property-read Collection<int, SeatSegment> $seatSegments
 */
#[Fillable(['route_id', 'vehicle_id', 'departure_at', 'status'])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return BelongsTo<TravelRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelRoute::class, 'route_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<SeatSegment, $this>
     */
    public function seatSegments(): HasMany
    {
        return $this->hasMany(SeatSegment::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return Collection<int, Stop>
     */
    public function stops(): Collection
    {
        return $this->route->stops;
    }

    public function stopCount(): int
    {
        return $this->stops()->count();
    }

    /**
     * Valida e cria o trecho entre duas paradas desta viagem.
     */
    public function leg(int $origin, int $destination): Leg
    {
        if ($origin < 0 || $destination >= $this->stopCount() || $origin >= $destination) {
            throw new InvalidArgumentException("Trecho {$origin} -> {$destination} não existe nesta viagem.");
        }

        return new Leg($origin, $destination);
    }

    public function departureAtStop(int $stopIndex): CarbonImmutable
    {
        $stop = $this->stops()->firstWhere('sequence', $stopIndex);

        return $this->departure_at->addMinutes($stop->minutes_from_origin ?? 0);
    }

    public function isBookable(): bool
    {
        return $this->status === TripStatus::Scheduled && $this->departure_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_at' => 'immutable_datetime',
            'status' => TripStatus::class,
        ];
    }
}
