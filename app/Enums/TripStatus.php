<?php

declare(strict_types=1);

namespace App\Enums;

enum TripStatus: string
{
    case Scheduled = 'scheduled';
    case Departed = 'departed';
    case Cancelled = 'cancelled';
}
