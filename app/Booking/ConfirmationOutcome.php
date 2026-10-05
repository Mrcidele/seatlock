<?php

declare(strict_types=1);

namespace App\Booking;

enum ConfirmationOutcome: string
{
    /** Assentos gravados em seat_segments e pedido pago. */
    case Confirmed = 'confirmed';
    /** Já estava pago (webhook/retry duplicado): nada a fazer. */
    case AlreadyPaid = 'already_paid';
    /** Alguém passou na frente: o UNIQUE de seat_segments barrou. Estornar. */
    case SeatsTaken = 'seats_taken';
    /** Pedido cancelado/reembolsado: o pagamento não pode ser aproveitado. Estornar. */
    case NotConfirmable = 'not_confirmable';

    public function requiresRefund(): bool
    {
        return $this === self::SeatsTaken || $this === self::NotConfirmable;
    }
}
