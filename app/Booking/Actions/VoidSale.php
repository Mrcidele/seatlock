<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Events\SeatReleased;
use App\Models\Order;
use App\Models\SeatSegment;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz uma venda confirmada: apaga as linhas de seat_segments (o assento
 * volta a ficar disponível), cancela reservas e revoga bilhetes.
 */
final readonly class VoidSale
{
    public function handle(Order $order, OrderStatus $to, string $reason, int $refundedCents = 0): Order
    {
        $voided = DB::transaction(function () use ($order, $to, $reason, $refundedCents): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $reservationIds = $locked->reservations()->pluck('id');

            SeatSegment::query()->whereIn('reservation_id', $reservationIds)->delete();
            Ticket::query()->whereIn('reservation_id', $reservationIds)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $locked->reservations()->update(['status' => ReservationStatus::Cancelled]);

            $locked->refunded_cents = $refundedCents;
            $locked->transitionTo($to, $reason);

            return $locked;
        });

        event(SeatReleased::forLeg($voided->trip_id, $voided->seatIds(), $voided->leg()));

        return $voided;
    }
}
