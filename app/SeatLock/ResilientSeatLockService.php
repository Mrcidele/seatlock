<?php

declare(strict_types=1);

namespace App\SeatLock;

use App\ValueObjects\Leg;
use Carbon\CarbonImmutable;
use Closure;
use Psr\Log\LoggerInterface;
use RedisException;

/**
 * Decorator "fail-open": se o Redis estiver fora do ar, o fluxo de compra
 * continua sem lock temporário. Isso piora a UX (mais conflitos na hora de
 * pagar), mas não a consistência: o UNIQUE de seat_segments impede a venda
 * dupla e o pagamento perdedor é estornado automaticamente.
 */
final readonly class ResilientSeatLockService implements SeatLockService
{
    public function __construct(
        private SeatLockService $inner,
        private LoggerInterface $logger,
    ) {}

    public function acquire(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult
    {
        return $this->guard(
            fn (): LockResult => $this->inner->acquire($tripId, $seatIds, $leg, $owner, $ttlSeconds),
            fn (): LockResult => LockResult::degraded(CarbonImmutable::now()->addSeconds($ttlSeconds)),
            'acquire',
        );
    }

    public function release(string $tripId, array $seatIds, Leg $leg, string $owner): int
    {
        return $this->guard(
            fn (): int => $this->inner->release($tripId, $seatIds, $leg, $owner),
            fn (): int => 0,
            'release',
        );
    }

    public function renew(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult
    {
        return $this->guard(
            fn (): LockResult => $this->inner->renew($tripId, $seatIds, $leg, $owner, $ttlSeconds),
            fn (): LockResult => LockResult::degraded(CarbonImmutable::now()->addSeconds($ttlSeconds)),
            'renew',
        );
    }

    public function heldUntil(string $tripId, array $seatIds, Leg $leg, string $owner): ?CarbonImmutable
    {
        $ttl = config('seatlock.locks.ttl');

        return $this->guard(
            fn (): ?CarbonImmutable => $this->inner->heldUntil($tripId, $seatIds, $leg, $owner),
            fn (): CarbonImmutable => CarbonImmutable::now()->addSeconds(is_int($ttl) ? $ttl : 600),
            'heldUntil',
        );
    }

    public function owners(string $tripId, array $seatIds, Leg $leg): array
    {
        return $this->guard(
            fn (): array => $this->inner->owners($tripId, $seatIds, $leg),
            fn (): array => [],
            'owners',
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @param  Closure(): T  $fallback
     * @return T
     */
    private function guard(Closure $operation, Closure $fallback, string $name): mixed
    {
        try {
            return $operation();
        } catch (RedisException|LockBackendUnavailable $e) {
            $this->logger->warning('seat_lock.degraded', [
                'operation' => $name,
                'error' => $e->getMessage(),
            ]);

            return $fallback();
        }
    }
}
