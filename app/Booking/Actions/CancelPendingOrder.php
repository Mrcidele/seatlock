<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Booking\Exceptions\OrderNotModifiable;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Events\SeatReleased;
use App\Models\Order;
use App\SeatLock\SeatLockService;
use Illuminate\Support\Facades\DB;

/**
 * Desistência antes do pagamento: libera os assentos imediatamente.
 */
final readonly class CancelPendingOrder
{
    public function __construct(private SeatLockService $locks) {}

    public function handle(Order $order, string $reason = 'customer_request'): Order
    {
        $order = DB::transaction(function () use ($order, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Pending) {
                throw OrderNotModifiable::notPending();
            }

            $locked->reservations()->update(['status' => ReservationStatus::Released]);
            $locked->transitionTo(OrderStatus::Cancelled, $reason);

            return $locked;
        });

        $this->locks->release($order->trip_id, $order->seatIds(), $order->leg(), $order->lock_owner);
        event(SeatReleased::forLeg($order->trip_id, $order->seatIds(), $order->leg()));

        return $order;
    }
}
