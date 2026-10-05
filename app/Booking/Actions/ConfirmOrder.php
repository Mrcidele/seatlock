<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Booking\ConfirmationOutcome;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Events\SeatReleased;
use App\Events\SeatSold;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\SeatSegment;
use App\Models\Ticket;
use App\Observability\BookingMetrics;
use App\SeatLock\SeatLockService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Converte um pedido pago em venda definitiva.
 *
 * Tudo acontece numa transação com o pedido travado (SELECT ... FOR UPDATE),
 * o que serializa a confirmação com a expiração e com webhooks duplicados.
 * A inserção em seat_segments roda num savepoint: se o UNIQUE barrar (alguém
 * passou na frente), o pedido não é pago e o chamador deve estornar.
 */
final readonly class ConfirmOrder
{
    public function __construct(private SeatLockService $locks) {}

    public function handle(string $orderId): ConfirmationOutcome
    {
        [$outcome, $order] = DB::transaction(function () use ($orderId): array {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            if ($order->status === OrderStatus::Paid) {
                return [ConfirmationOutcome::AlreadyPaid, $order];
            }

            if (! $order->status->canTransitionTo(OrderStatus::Paid)) {
                return [ConfirmationOutcome::NotConfirmable, $order];
            }

            $reservations = $order->reservations()->get();
            $rows = $reservations->flatMap(fn (Reservation $r): array => $r->segmentRows())->values()->all();

            try {
                DB::transaction(fn (): bool => SeatSegment::query()->insert($rows));
            } catch (UniqueConstraintViolationException) {
                $order->reservations()->update(['status' => ReservationStatus::Released]);

                if ($order->status === OrderStatus::Pending) {
                    $order->transitionTo(OrderStatus::Cancelled, 'seat_unavailable');
                }

                return [ConfirmationOutcome::SeatsTaken, $order];
            }

            $order->reservations()->update(['status' => ReservationStatus::Confirmed]);
            $order->transitionTo(OrderStatus::Paid);

            foreach ($reservations as $reservation) {
                Ticket::query()->create(['reservation_id' => $reservation->id, 'issued_at' => now()]);
            }

            return [ConfirmationOutcome::Confirmed, $order];
        });

        if ($outcome !== ConfirmationOutcome::AlreadyPaid) {
            // Vendido (ou perdido): o lock temporário não serve mais para nada.
            $this->locks->release($order->trip_id, $order->seatIds(), $order->leg(), $order->lock_owner);
        }

        match ($outcome) {
            ConfirmationOutcome::Confirmed => BookingMetrics::orderPaid($order),
            ConfirmationOutcome::SeatsTaken => BookingMetrics::seatsTakenAtConfirmation(),
            default => null,
        };

        match ($outcome) {
            ConfirmationOutcome::Confirmed => event(SeatSold::forLeg($order->trip_id, $order->seatIds(), $order->leg())),
            ConfirmationOutcome::SeatsTaken => event(SeatReleased::forLeg($order->trip_id, $order->seatIds(), $order->leg())),
            default => null,
        };

        return $outcome;
    }
}
