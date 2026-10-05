<?php

declare(strict_types=1);

namespace App\SeatLock;

use App\ValueObjects\Leg;

/**
 * Chaves no formato lock:{trip}:{seat}:{segment}. As chaves usam a hash tag
 * {trip}, então todas as chaves de uma viagem caem no mesmo slot do Redis
 * Cluster e o script Lua continua atômico.
 */
final class LockKeys
{
    public static function key(string $tripId, string $seatId, int $segment): string
    {
        return "lock:{{$tripId}}:{$seatId}:{$segment}";
    }

    /**
     * Chaves em ordem assento-maior: [seat0/seg0, seat0/seg1, seat1/seg0, ...].
     *
     * @param  list<string>  $seatIds
     * @return list<array{key: string, seat_id: string, segment: int}>
     */
    public static function forSeats(string $tripId, array $seatIds, Leg $leg): array
    {
        $keys = [];

        foreach ($seatIds as $seatId) {
            foreach ($leg->segments() as $segment) {
                $keys[] = ['key' => self::key($tripId, $seatId, $segment), 'seat_id' => $seatId, 'segment' => $segment];
            }
        }

        return $keys;
    }
}
