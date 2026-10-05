<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação de um assento para um trecho específico de uma viagem.
 */
enum SeatStatus: string
{
    case Available = 'available';
    case Locked = 'locked';
    case Sold = 'sold';
}
