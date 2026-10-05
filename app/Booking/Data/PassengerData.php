<?php

declare(strict_types=1);

namespace App\Booking\Data;

final readonly class PassengerData
{
    public function __construct(
        public string $seatId,
        public string $name,
        public string $document,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}
}
