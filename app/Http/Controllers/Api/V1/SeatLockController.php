<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Booking\Actions\LockSeats;
use App\Booking\LockOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LockSeatsRequest;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SeatLockController extends Controller
{
    /**
     * Trava temporariamente os assentos para o carrinho (tudo ou nada).
     */
    public function store(LockSeatsRequest $request, Trip $trip, LockSeats $lockSeats): JsonResponse
    {
        $leg = $request->legFor($trip);
        $seatIds = $request->seatIds();
        $result = $lockSeats->handle($trip, $leg, $seatIds, LockOwner::fromRequest($request));

        return new JsonResponse([
            'data' => [
                'trip_id' => $trip->id,
                'seat_ids' => $seatIds,
                'leg' => ['origin' => $leg->origin, 'destination' => $leg->destination],
                'expires_at' => $result->expiresAt?->toIso8601String(),
                'server_time' => CarbonImmutable::now()->toIso8601String(),
                'degraded' => $result->degraded,
            ],
        ], 201);
    }

    /**
     * Libera os assentos travados pelo carrinho.
     */
    public function destroy(LockSeatsRequest $request, Trip $trip, LockSeats $lockSeats): Response
    {
        $lockSeats->release($trip, $request->legFor($trip), $request->seatIds(), LockOwner::fromRequest($request));

        return response()->noContent();
    }
}
