<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

final class InvalidOrderTransition extends DomainException
{
    public static function between(Order $order, OrderStatus $from, OrderStatus $to): self
    {
        return new self("Pedido {$order->id} não pode ir de {$from->value} para {$to->value}.");
    }
}
