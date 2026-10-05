<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Booking\AlternativeSeats;
use App\Booking\BookingSettings;
use App\Booking\Exceptions\SeatsUnavailable;
use App\Booking\Exceptions\TripNotBookable;
use App\Booking\SeatAvailability;
use App\Events\SeatLocked;
use App\Events\SeatReleased;
use App\Models\Seat;
use App\Models\Trip;
use App\Observability\BookingMetrics;
use App\SeatLock\LockResult;
use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;
use Illuminate\Validation\ValidationException;

/**
 * Primeiro passo do fluxo: trava os assentos escolhidos para o carrinho.
 */
final readonly class LockSeats
{
    public function __construct(
        private SeatLockService $locks,
        private SeatAvailability $availability,
        private AlternativeSeats $alternatives,
    ) {}

    /**
     * @param  list<string>  $seatIds
     *
     * @throws SeatsUnavailable
     * @throws TripNotBookable
     */
    public function handle(Trip $trip, Leg $leg, array $seatIds, string $owner): LockResult
    {
        if (! $trip->isBookable()) {
            throw new TripNotBookable;
        }

        $seatIds = array_values(array_unique($seatIds));
        $this->assertSeatsBelongToTrip($trip, $seatIds);

        // Checagem rápida no banco: não faz sentido travar assento já vendido.
        $sold = $this->availability->unavailableAmong($trip, $leg, $seatIds);

        if ($sold !== []) {
            throw $this->unavailable($trip, $leg, $sold, $seatIds);
        }

        $result = $this->locks->acquire($trip->id, $seatIds, $leg, $owner, BookingSettings::lockTtl());

        if (! $result->acquired) {
            BookingMetrics::lockConflict();

            throw $this->unavailable($trip, $leg, $result->conflictingSeatIds, $seatIds);
        }

        $result->degraded ? BookingMetrics::lockDegraded() : BookingMetrics::lockAcquired(count($seatIds));

        event(SeatLocked::forLeg($trip->id, $seatIds, $leg));

        return $result;
    }

    /**
     * @param  list<string>  $seatIds
     */
    public function release(Trip $trip, Leg $leg, array $seatIds, string $owner): int
    {
        $seatIds = array_values(array_unique($seatIds));
        $released = $this->locks->release($trip->id, $seatIds, $leg, $owner);

        if ($released > 0) {
            event(SeatReleased::forLeg($trip->id, $seatIds, $leg));
        }

        return $released;
    }

    /**
     * @param  list<string>  $seatIds
     */
    private function assertSeatsBelongToTrip(Trip $trip, array $seatIds): void
    {
        $count = Seat::query()->where('vehicle_id', $trip->vehicle_id)->whereIn('id', $seatIds)->count();

        if ($count !== count($seatIds)) {
            throw ValidationException::withMessages(['seat_ids' => 'Um ou mais assentos não pertencem ao veículo desta viagem.']);
        }
    }

    /**
     * @param  list<string>  $conflicting
     * @param  list<string>  $requested
     */
    private function unavailable(Trip $trip, Leg $leg, array $conflicting, array $requested): SeatsUnavailable
    {
        return new SeatsUnavailable(
            $conflicting,
            $this->alternatives->suggest($trip, $leg, $conflicting, $requested),
        );
    }
}
