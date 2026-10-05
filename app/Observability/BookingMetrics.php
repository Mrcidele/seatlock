<?php

declare(strict_types=1);

namespace App\Observability;

use App\Models\Order;
use Laravel\Pulse\Facades\Pulse;

/**
 * Métricas de negócio enviadas ao Pulse. Um só lugar para os nomes, assim o
 * card do dashboard e o código que registra não divergem.
 */
final class BookingMetrics
{
    public const string TYPE = 'booking';

    public const string TIME_TO_PAY = 'booking_time_to_pay';

    public static function lockAcquired(int $seats): void
    {
        Pulse::record(self::TYPE, 'lock_acquired', $seats)->sum()->count();
    }

    public static function lockConflict(): void
    {
        Pulse::record(self::TYPE, 'lock_conflict')->count();
    }

    public static function lockDegraded(): void
    {
        Pulse::record(self::TYPE, 'lock_degraded')->count();
    }

    public static function orderCreated(): void
    {
        Pulse::record(self::TYPE, 'order_created')->count();
    }

    public static function orderPaid(Order $order): void
    {
        Pulse::record(self::TYPE, 'order_paid')->count();

        $seconds = max(0, (int) $order->created_at->diffInSeconds($order->paid_at ?? now()));
        Pulse::record(self::TIME_TO_PAY, 'seconds', $seconds)->avg()->max();
    }

    public static function orderExpired(): void
    {
        Pulse::record(self::TYPE, 'order_expired')->count();
    }

    public static function seatsTakenAtConfirmation(): void
    {
        Pulse::record(self::TYPE, 'seats_taken_refund')->count();
    }
}
