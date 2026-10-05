<?php

declare(strict_types=1);

namespace App\Layout;

enum CellKind: string
{
    case Seat = 'seat';
    case Aisle = 'aisle';
    case Empty = 'empty';
    case Toilet = 'toilet';
    case Stairs = 'stairs';

    public static function fromCode(string $code): ?self
    {
        return match ($code) {
            'C', 'L', 'P' => self::Seat,
            '_' => self::Aisle,
            '.' => self::Empty,
            'W' => self::Toilet,
            'E' => self::Stairs,
            default => null,
        };
    }
}
