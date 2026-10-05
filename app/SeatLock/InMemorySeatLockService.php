<?php

declare(strict_types=1);

namespace App\SeatLock;

use App\ValueObjects\Leg;
use Carbon\CarbonImmutable;

/**
 * Implementação em memória com a mesma semântica do Redis, para testes.
 * Respeita o relógio do Laravel (travel/freezeTime) para simular expiração.
 */
final class InMemorySeatLockService implements SeatLockService
{
    /** @var array<string, array{owner: string, expires_at: CarbonImmutable}> */
    private array $locks = [];

    public function acquire(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult
    {
        $keys = LockKeys::forSeats($tripId, $seatIds, $leg);
        $conflicts = [];

        foreach ($keys as $key) {
            $current = $this->current($key['key']);

            if ($current !== null && $current['owner'] !== $owner) {
                $conflicts[] = $key['seat_id'];
            }
        }

        if ($conflicts !== []) {
            return LockResult::conflict($conflicts);
        }

        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds($ttlSeconds);

        foreach ($keys as $key) {
            $current = $this->current($key['key']);

            if ($current === null) {
                $this->locks[$key['key']] = ['owner' => $owner, 'expires_at' => $now->addSeconds($ttlSeconds)];
            } elseif ($current['expires_at']->lessThan($expiresAt)) {
                $expiresAt = $current['expires_at'];
            }
        }

        return LockResult::acquired($expiresAt);
    }

    public function release(string $tripId, array $seatIds, Leg $leg, string $owner): int
    {
        $released = 0;

        foreach (LockKeys::forSeats($tripId, $seatIds, $leg) as $key) {
            if (($this->current($key['key'])['owner'] ?? null) === $owner) {
                unset($this->locks[$key['key']]);
                $released++;
            }
        }

        return $released;
    }

    public function renew(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult
    {
        $keys = LockKeys::forSeats($tripId, $seatIds, $leg);

        foreach ($keys as $key) {
            if (($this->current($key['key'])['owner'] ?? null) !== $owner) {
                return LockResult::notHeld();
            }
        }

        $expiresAt = CarbonImmutable::now()->addSeconds($ttlSeconds);

        foreach ($keys as $key) {
            $this->locks[$key['key']]['expires_at'] = $expiresAt;
        }

        return LockResult::acquired($expiresAt);
    }

    public function heldUntil(string $tripId, array $seatIds, Leg $leg, string $owner): ?CarbonImmutable
    {
        if ($seatIds === []) {
            return null;
        }

        $earliest = null;

        foreach (LockKeys::forSeats($tripId, $seatIds, $leg) as $key) {
            $current = $this->current($key['key']);

            if ($current === null || $current['owner'] !== $owner) {
                return null;
            }

            if ($earliest === null || $current['expires_at']->lessThan($earliest)) {
                $earliest = $current['expires_at'];
            }
        }

        return $earliest;
    }

    public function owners(string $tripId, array $seatIds, Leg $leg): array
    {
        $owners = [];

        foreach (LockKeys::forSeats($tripId, $seatIds, $leg) as $key) {
            $current = $this->current($key['key']);

            if ($current !== null && ! isset($owners[$key['seat_id']])) {
                $owners[$key['seat_id']] = $current['owner'];
            }
        }

        return $owners;
    }

    /**
     * @return array{owner: string, expires_at: CarbonImmutable}|null
     */
    private function current(string $key): ?array
    {
        $lock = $this->locks[$key] ?? null;

        if ($lock !== null && $lock['expires_at']->lessThanOrEqualTo(CarbonImmutable::now())) {
            unset($this->locks[$key]);

            return null;
        }

        return $lock;
    }
}
