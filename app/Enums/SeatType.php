<?php

declare(strict_types=1);

namespace App\Enums;

enum SeatType: string
{
    case Conventional = 'conventional';
    case Sleeper = 'sleeper';
    case Accessible = 'accessible';

    public static function fromLayoutCode(string $code): ?self
    {
        return match ($code) {
            'C' => self::Conventional,
            'L' => self::Sleeper,
            'P' => self::Accessible,
            default => null,
        };
    }

    /**
     * Multiplicador de tarifa em pontos-base (10_000 = 1x).
     */
    public function fareMultiplierBasisPoints(): int
    {
        return match ($this) {
            self::Conventional, self::Accessible => 10_000,
            self::Sleeper => 15_000,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Conventional => 'Convencional',
            self::Sleeper => 'Leito',
            self::Accessible => 'PCD',
        };
    }
}
