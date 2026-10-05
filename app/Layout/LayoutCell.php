<?php

declare(strict_types=1);

namespace App\Layout;

use App\Enums\SeatType;

final readonly class LayoutCell
{
    public function __construct(
        public int $deck,
        public int $row,
        public int $column,
        public CellKind $kind,
        public ?SeatType $seatType = null,
        public ?string $seatNumber = null,
    ) {}
}
