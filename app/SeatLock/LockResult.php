<?php

declare(strict_types=1);

namespace App\SeatLock;

use Carbon\CarbonImmutable;

final readonly class LockResult
{
    /**
     * @param  list<string>  $conflictingSeatIds
     */
    private function __construct(
        public bool $acquired,
        public array $conflictingSeatIds,
        public ?CarbonImmutable $expiresAt,
        public bool $degraded = false,
    ) {}

    public static function acquired(CarbonImmutable $expiresAt): self
    {
        return new self(true, [], $expiresAt);
    }

    /**
     * Redis indisponível: seguimos sem lock e o banco protege na confirmação.
     */
    public static function degraded(CarbonImmutable $expiresAt): self
    {
        return new self(true, [], $expiresAt, true);
    }

    /**
     * @param  list<string>  $conflictingSeatIds
     */
    public static function conflict(array $conflictingSeatIds): self
    {
        return new self(false, array_values(array_unique($conflictingSeatIds)), null);
    }

    public static function notHeld(): self
    {
        return new self(false, [], null);
    }
}
