<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Trecho de uma viagem, entre o índice da parada de embarque e o da parada de
 * desembarque. O segmento i liga a parada i à parada i+1, então o trecho
 * A(0) -> C(2) ocupa os segmentos 0 e 1.
 */
final readonly class Leg
{
    public function __construct(
        public int $origin,
        public int $destination,
    ) {
        if ($origin < 0 || $destination <= $origin) {
            throw new InvalidArgumentException("Trecho inválido: {$origin} -> {$destination}");
        }
    }

    /**
     * @return list<int>
     */
    public function segments(): array
    {
        return range($this->origin, $this->destination - 1);
    }

    public function lastSegment(): int
    {
        return $this->destination - 1;
    }

    public function overlaps(self $other): bool
    {
        return $this->origin < $other->destination && $other->origin < $this->destination;
    }

    public function equals(self $other): bool
    {
        return $this->origin === $other->origin && $this->destination === $other->destination;
    }
}
