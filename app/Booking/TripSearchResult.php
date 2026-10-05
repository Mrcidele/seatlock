<?php

declare(strict_types=1);

namespace App\Booking;

use App\Models\Trip;
use App\ValueObjects\Leg;
use App\ValueObjects\Money;

final readonly class TripSearchResult
{
    public function __construct(
        public Trip $trip,
        public Leg $leg,
        public int $availableSeats,
        public Money $fromPrice,
    ) {}
}
