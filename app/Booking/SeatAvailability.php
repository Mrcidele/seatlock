<?php

declare(strict_types=1);

namespace App\Booking;

use App\Models\Seat;
use App\Models\SeatSegment;
use App\Models\Trip;
use App\ValueObjects\Leg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Disponibilidade por trecho, consultada no banco (fonte da verdade).
 *
 * Um assento está livre para A -> C se nenhum dos segmentos entre A e C
 * estiver ocupado em seat_segments.
 */
final readonly class SeatAvailability
{
    /**
     * @return Collection<int, Seat>
     */
    public function availableSeats(Trip $trip, Leg $leg): Collection
    {
        return Seat::query()
            ->where('vehicle_id', $trip->vehicle_id)
            ->whereNotExists(function (QueryBuilder $query) use ($trip, $leg): void {
                $query->selectRaw('1')
                    ->from('seat_segments')
                    ->where('seat_segments.trip_id', $trip->id)
                    ->whereColumn('seat_segments.seat_id', 'seats.id')
                    ->whereBetween('seat_segments.segment_index', [$leg->origin, $leg->lastSegment()]);
            })
            ->orderBy('deck')->orderBy('row')->orderBy('column')
            ->get();
    }

    /**
     * IDs dos assentos ocupados em qualquer segmento do trecho.
     *
     * @return array<string, true>
     */
    public function soldSeatIds(Trip $trip, Leg $leg): array
    {
        $ids = $this->segmentsInLeg($trip, $leg)
            ->distinct()
            ->pluck('seat_id');

        $sold = [];
        foreach ($ids as $id) {
            if (is_string($id)) {
                $sold[$id] = true;
            }
        }

        return $sold;
    }

    /**
     * @param  list<string>  $seatIds
     * @return list<string> os assentos da lista que já estão vendidos no trecho
     */
    public function unavailableAmong(Trip $trip, Leg $leg, array $seatIds): array
    {
        if ($seatIds === []) {
            return [];
        }

        return array_values(array_filter(
            $this->segmentsInLeg($trip, $leg)->whereIn('seat_id', $seatIds)->distinct()->pluck('seat_id')->all(),
            is_string(...),
        ));
    }

    public function isAvailable(Trip $trip, string $seatId, Leg $leg): bool
    {
        return $this->unavailableAmong($trip, $leg, [$seatId]) === [];
    }

    /**
     * @return Builder<SeatSegment>
     */
    private function segmentsInLeg(Trip $trip, Leg $leg): Builder
    {
        return SeatSegment::query()
            ->where('trip_id', $trip->id)
            ->whereBetween('segment_index', [$leg->origin, $leg->lastSegment()]);
    }
}
