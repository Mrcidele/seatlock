<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Tickets\TicketQrCode;
use App\Tickets\TicketSigner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TicketController extends Controller
{
    /**
     * Bilhete com o conteúdo assinado do QR Code.
     */
    public function show(Request $request, string $ticket, TicketSigner $signer): JsonResponse
    {
        $ticket = $this->findOwned($request, $ticket);

        return new JsonResponse(['data' => [
            'id' => $ticket->id,
            'reservation_id' => $ticket->reservation_id,
            'qr_code' => $signer->sign($ticket),
            'issued_at' => $ticket->issued_at->toIso8601String(),
            'used_at' => $ticket->used_at?->toIso8601String(),
            'valid' => $ticket->isValid(),
        ]]);
    }

    /**
     * Bilhete em PDF com QR Code assinado (HMAC).
     */
    public function pdf(Request $request, string $ticket, TicketSigner $signer): Response
    {
        $ticket = $this->findOwned($request, $ticket);
        $reservation = $ticket->reservation;
        $trip = $reservation->trip;
        $stops = $trip->stops()->keyBy('sequence');
        $document = $reservation->passenger->document;

        return Pdf::loadView('tickets.pdf', [
            'ticket' => $ticket,
            'reservation' => $reservation,
            'order' => $reservation->order,
            'passenger' => $reservation->passenger,
            'maskedDocument' => str_repeat('*', max(0, strlen($document) - 3)).substr($document, -3),
            'seat' => $reservation->seat,
            'origin' => $stops->get($reservation->origin_index),
            'destination' => $stops->get($reservation->destination_index),
            'departure' => $trip->departureAtStop($reservation->origin_index),
            'qr' => TicketQrCode::dataUri($signer->sign($ticket)),
        ])->download("bilhete-{$reservation->seat->number}-{$ticket->id}.pdf");
    }

    private function findOwned(Request $request, string $id): Ticket
    {
        $user = $request->user();
        assert($user instanceof User);

        return Ticket::query()
            ->with(['reservation.order', 'reservation.passenger', 'reservation.seat', 'reservation.trip.route.stops'])
            ->whereNull('revoked_at')
            ->whereHas('reservation.order', fn ($query) => $query->where('user_id', $user->id))
            ->findOrFail($id);
    }
}
