<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Enums\OrderStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Validação no embarque: confere a assinatura e marca o bilhete como usado
 * com um UPDATE condicional, então dois validadores lendo o mesmo QR ao
 * mesmo tempo nunca aceitam os dois.
 */
final readonly class ValidateBoarding
{
    public function __construct(private TicketSigner $signer) {}

    public function handle(string $code, User $operator, ?string $expectedTripId = null): Ticket
    {
        $claims = $this->signer->verify($code);

        if ($expectedTripId !== null && $claims['trip_id'] !== $expectedTripId) {
            throw new TicketRejected('Este bilhete é de outra viagem.', 'ticket-wrong-trip');
        }

        $ticket = Ticket::query()->with(['reservation.order', 'reservation.passenger', 'reservation.seat'])->find($claims['ticket_id']);

        if ($ticket === null || $ticket->revoked_at !== null || $ticket->reservation->order->status !== OrderStatus::Paid) {
            throw new TicketRejected('Bilhete cancelado ou remarcado.', 'ticket-revoked');
        }

        $marked = DB::table('tickets')
            ->where('id', $ticket->id)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->update(['used_at' => now(), 'used_by' => $operator->id, 'updated_at' => now()]);

        if ($marked === 0) {
            $ticket->refresh();

            throw new TicketRejected('Este bilhete já foi utilizado.', 'ticket-already-used', $ticket->used_at);
        }

        return $ticket->refresh();
    }
}
