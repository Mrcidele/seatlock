<?php

declare(strict_types=1);

namespace App\Booking;

final class BookingSettings
{
    public static function lockTtl(): int
    {
        return self::int('seatlock.locks.ttl', 600);
    }

    public static function maxRenewals(): int
    {
        return self::int('seatlock.locks.max_renewals', 1);
    }

    public static function maxSeatsPerOrder(): int
    {
        return self::int('seatlock.locks.max_seats_per_order', 6);
    }

    public static function reconciliationGrace(): int
    {
        return self::int('seatlock.orders.reconciliation_grace_seconds', 30);
    }

    public static function currency(): string
    {
        $currency = config('seatlock.currency');

        return is_string($currency) ? $currency : 'BRL';
    }

    private static function int(string $key, int $default): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
