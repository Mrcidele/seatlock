<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    /** Aguardando pagamento; protegida apenas pelo lock no Redis. */
    case Pending = 'pending';
    /** Paga; ocupa linhas em seat_segments. */
    case Confirmed = 'confirmed';
    /** Pedido expirou ou foi abandonado antes do pagamento. */
    case Released = 'released';
    /** Cancelada após a venda (com ou sem reembolso). */
    case Cancelled = 'cancelled';
}
