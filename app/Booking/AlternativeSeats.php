<?php

declare(strict_types=1);

namespace App\Booking;

use App\Models\Seat;
use App\Models\Trip;
use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;

/**
 * Sugere assentos livres parecidos com os que o cliente perdeu: mesmo tipo,
 * mais próximos no mapa (mesmo andar primeiro).
 */
final readonly class AlternativeSeats
{
    public function __construct(
        private SeatAvailability $availability,
        private SeatLockService $locks,
    ) {}

    /**
     * @param  list<string>  $lostSeatIds
     * @param  list<string>  $excludeSeatIds
     * @return list<Seat>
     */
    public function suggest(Trip $trip, Leg $leg, array $lostSeatIds, array $excludeSeatIds = [], int $limit = 5): array
    {
        $lost = Seat::query()->whereIn('id', $lostSeatIds)->get();

        if ($lost->isEmpty()) {
            return [];
        }

        $candidates = $this->availability->availableSeats($trip, $leg)
            ->reject(fn (Seat $seat): bool => in_array($seat->id, [...$lostSeatIds, ...$excludeSeatIds], true));

        $locked = $this->locks->owners($trip->id, array_values($candidates->map(fn (Seat $seat): string => $seat->id)->all()), $leg);
        $types = $lost->map(fn (Seat $seat): string => $seat->type->value)->unique()->all();

        $distance = function (Seat $candidate) use ($lost): int {
            $best = PHP_INT_MAX;

            foreach ($lost as $seat) {
                $best = min($best, abs($seat->deck - $candidate->deck) * 100 + abs($seat->row - $candidate->row) * 2 + abs($seat->column - $candidate->column));
            }

            return $best;
        };

        return array_values(
            $candidates
                ->reject(fn (Seat $seat): bool => isset($locked[$seat->id]))
                ->sortBy([
                    fn (Seat $a, Seat $b): int => (int) ! in_array($a->type->value, $types, true) <=> (int) ! in_array($b->type->value, $types, true),
                    fn (Seat $a, Seat $b): int => $distance($a) <=> $distance($b),
                ])
                ->take($limit)
                ->all(),
        );
    }
}
