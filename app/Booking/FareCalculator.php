<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\SeatType;
use App\Models\Trip;
use App\ValueObjects\Leg;
use App\ValueObjects\Money;
use InvalidArgumentException;

/**
 * Tarifa de um trecho = diferença das tarifas acumuladas das paradas,
 * multiplicada pelo fator do tipo de assento.
 */
final readonly class FareCalculator
{
    public function priceFor(Trip $trip, Leg $leg, SeatType $type): Money
    {
        $stops = $trip->stops()->keyBy('sequence');
        $origin = $stops->get($leg->origin);
        $destination = $stops->get($leg->destination);

        if ($origin === null || $destination === null) {
            throw new InvalidArgumentException('Trecho fora da linha da viagem.');
        }

        $base = Money::of(
            $destination->fare_from_origin_cents - $origin->fare_from_origin_cents,
            $this->currency(),
        );

        return $base->multiplyBasisPoints($type->fareMultiplierBasisPoints());
    }

    private function currency(): string
    {
        $currency = config('seatlock.currency');

        return is_string($currency) ? $currency : 'BRL';
    }
}
