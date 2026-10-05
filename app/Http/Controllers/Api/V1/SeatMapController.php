<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Booking\LockOwner;
use App\Booking\SeatMapBuilder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SeatMapRequest;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\User;
use App\SeatLock\SeatLockService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SeatMapController extends Controller
{
    /**
     * Mapa de assentos da viagem para o trecho informado.
     *
     * Vendidos vêm do banco; travados vêm do Redis. Com token e X-Cart-Id,
     * os assentos travados pelo próprio carrinho vêm com held_by_you = true.
     *
     * @unauthenticated
     */
    public function __invoke(SeatMapRequest $request, Trip $trip, SeatMapBuilder $builder, SeatLockService $locks): JsonResponse
    {
        $trip->load(['route.stops', 'vehicle.seats']);
        $leg = $request->legFor($trip);

        $seatIds = array_values($trip->vehicle->seats->map(fn (Seat $seat): string => $seat->id)->all());
        $user = auth('sanctum')->user();
        $cartId = $request->header(LockOwner::HEADER);
        $viewer = $user instanceof User && is_string($cartId) ? LockOwner::for($user, $cartId) : null;

        return new JsonResponse([
            'data' => $builder->build($trip, $leg, $locks->owners($trip->id, $seatIds, $leg), $viewer),
            'meta' => ['server_time' => CarbonImmutable::now()->toIso8601String()],
        ]);
    }
}
