<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Booking\Exceptions\OrderNotModifiable;
use App\Booking\Exceptions\SeatsUnavailable;
use App\Booking\Exceptions\TripNotBookable;
use App\Booking\FareCalculator;
use App\Enums\OrderStatus;
use App\Events\SeatReleased;
use App\Events\SeatSold;
use App\Models\Order;
use App\Models\Seat;
use App\Models\SeatSegment;
use App\Models\Ticket;
use App\Models\Trip;
use App\SeatLock\SeatLockService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Remarcação: move todas as reservas do pedido para outra viagem da mesma
 * linha, no mesmo trecho. A troca de seat_segments acontece numa transação;
 * se o UNIQUE barrar algum assento novo, nada muda.
 */
final readonly class RebookOrder
{
    public function __construct(
        private FareCalculator $fares,
        private SeatLockService $locks,
    ) {}

    /**
     * @param  array<string, string>  $seatByReservation  reservation_id => novo seat_id
     */
    public function handle(Order $order, Trip $newTrip, array $seatByReservation): Order
    {
        $order->loadMissing(['trip.route.stops', 'reservations']);
        $oldTrip = $order->trip;
        $leg = $order->leg();

        if ($order->status !== OrderStatus::Paid) {
            throw OrderNotModifiable::notPending();
        }

        $deadline = $oldTrip->departureAtStop($leg->origin)->subHours(self::minHours());
        if (now()->greaterThan($deadline)) {
            throw new OrderNotModifiable('O prazo para remarcar esta passagem já terminou.', 'rebooking-window-closed');
        }

        if ($newTrip->id === $oldTrip->id || $newTrip->route_id !== $oldTrip->route_id) {
            throw ValidationException::withMessages(['trip_id' => 'Escolha outra viagem da mesma linha.']);
        }

        if (! $newTrip->isBookable()) {
            throw new TripNotBookable;
        }

        $reservationIds = $order->reservations->pluck('id')->map(fn (mixed $id): string => (string) $id)->sort()->values()->all();
        $requested = array_keys($seatByReservation);
        sort($requested);

        if ($requested !== $reservationIds) {
            throw ValidationException::withMessages(['seats' => 'Informe um novo assento para cada reserva do pedido.']);
        }

        $newSeats = Seat::query()->where('vehicle_id', $newTrip->vehicle_id)->whereIn('id', array_values($seatByReservation))->get()->keyBy('id');

        if ($newSeats->count() !== count(array_unique($seatByReservation))) {
            throw ValidationException::withMessages(['seats' => 'Assento inválido para a nova viagem.']);
        }

        $lockedByOthers = array_keys($this->locks->owners($newTrip->id, array_values($seatByReservation), $leg));
        if ($lockedByOthers !== []) {
            throw new SeatsUnavailable($lockedByOthers);
        }

        foreach ($order->reservations as $reservation) {
            $seat = $newSeats->get($seatByReservation[$reservation->id]);
            assert($seat instanceof Seat);

            if ($this->fares->priceFor($newTrip, $leg, $seat->type)->greaterThan($reservation->price)) {
                throw ValidationException::withMessages(['seats' => "O assento {$seat->number} é mais caro que o original; escolha um do mesmo tipo."]);
            }
        }

        $oldSeatIds = $order->seatIds();

        try {
            DB::transaction(function () use ($order, $newTrip, $seatByReservation): void {
                Order::query()->lockForUpdate()->findOrFail($order->id);

                foreach ($order->reservations as $reservation) {
                    SeatSegment::query()->where('reservation_id', $reservation->id)->delete();
                    Ticket::query()->where('reservation_id', $reservation->id)->delete();

                    $reservation->update(['trip_id' => $newTrip->id, 'seat_id' => $seatByReservation[$reservation->id]]);

                    SeatSegment::query()->insert($reservation->segmentRows());
                    Ticket::query()->create(['reservation_id' => $reservation->id, 'issued_at' => now()]);
                }

                $order->update(['trip_id' => $newTrip->id]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new SeatsUnavailable(array_values($seatByReservation));
        }

        event(SeatReleased::forLeg($oldTrip->id, $oldSeatIds, $leg));
        event(SeatSold::forLeg($newTrip->id, array_values($seatByReservation), $leg));

        return $order->refresh();
    }

    private static function minHours(): int
    {
        $value = config('seatlock.rebooking_min_hours_before');

        return is_numeric($value) ? (int) $value : 3;
    }
}
