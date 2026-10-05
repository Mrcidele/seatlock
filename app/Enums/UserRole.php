<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Operator = 'operator';
    case Admin = 'admin';

    public function canValidateBoarding(): bool
    {
        return $this === self::Operator || $this === self::Admin;
    }
}
