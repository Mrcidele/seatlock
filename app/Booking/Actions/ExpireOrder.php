<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Events\SeatReleased;
use App\Models\Order;
use App\Observability\BookingMetrics;
use App\SeatLock\SeatLockService;
use Illuminate\Support\Facades\DB;

/**
 * Expira um pedido pendente cujo prazo passou e libera os assentos.
 * Idempotente: pedidos já pagos, renovados ou expirados são ignorados.
 */
final readonly class ExpireOrder
{
    public function __construct(private SeatLockService $locks) {}

    /**
     * @return bool se o pedido foi expirado agora
     */
    public function handle(string $orderId): bool
    {
        $order = DB::transaction(function () use ($orderId): ?Order {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($order === null || ! $order->isExpiredAt(now())) {
                return null;
            }

            $order->reservations()->update(['status' => ReservationStatus::Released]);
            $order->transitionTo(OrderStatus::Expired);

            return $order;
        });

        if ($order === null) {
            return false;
        }

        // O TTL do Redis já deve ter liberado; garantimos mesmo assim (só o dono apaga).
        $this->locks->release($order->trip_id, $order->seatIds(), $order->leg(), $order->lock_owner);
        event(SeatReleased::forLeg($order->trip_id, $order->seatIds(), $order->leg()));
        BookingMetrics::orderExpired();

        return true;
    }
}
