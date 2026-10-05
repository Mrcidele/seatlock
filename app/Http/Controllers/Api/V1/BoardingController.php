<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tickets\ValidateBoarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardingController extends Controller
{
    /**
     * Valida o QR Code no embarque e marca o bilhete como usado (uso único).
     * Restrito a operadores.
     */
    public function __invoke(Request $request, ValidateBoarding $validate): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role->canValidateBoarding(), 403, 'Apenas operadores validam embarque.');

        $request->validate([
            'code' => ['required', 'string', 'max:1000'],
            'trip_id' => ['nullable', 'string', 'ulid'],
        ]);

        $ticket = $validate->handle(
            $request->string('code')->toString(),
            $user,
            $request->filled('trip_id') ? $request->string('trip_id')->toString() : null,
        );
        $reservation = $ticket->reservation;

        return new JsonResponse(['data' => [
            'valid' => true,
            'ticket_id' => $ticket->id,
            'used_at' => $ticket->used_at?->toIso8601String(),
            'passenger' => $reservation->passenger->name,
            'seat' => $reservation->seat->number,
            'trip_id' => $reservation->trip_id,
            'leg' => ['origin' => $reservation->origin_index, 'destination' => $reservation->destination_index],
        ]]);
    }
}
