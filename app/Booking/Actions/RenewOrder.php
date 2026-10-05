<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Booking\BookingSettings;
use App\Booking\Exceptions\LockNotHeld;
use App\Booking\Exceptions\OrderNotModifiable;
use App\Booking\Jobs\ExpireOrderJob;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\SeatLock\SeatLockService;
use Illuminate\Support\Facades\DB;

/**
 * Estende o prazo do pedido (e do lock) por mais um TTL, no máximo N vezes.
 */
final readonly class RenewOrder
{
    public function __construct(private SeatLockService $locks) {}

    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Pending) {
                throw OrderNotModifiable::notPending();
            }

            if ($order->isExpiredAt(now())) {
                throw OrderNotModifiable::expired();
            }

            if ($order->lock_renewals >= BookingSettings::maxRenewals()) {
                throw OrderNotModifiable::renewalLimitReached();
            }

            $result = $this->locks->renew($order->trip_id, $order->seatIds(), $order->leg(), $order->lock_owner, BookingSettings::lockTtl());

            if (! $result->acquired || $result->expiresAt === null) {
                throw new LockNotHeld;
            }

            $order->expires_at = $result->expiresAt;
            $order->lock_renewals++;
            $order->save();

            ExpireOrderJob::scheduleFor($order);

            return $order;
        });
    }
}
