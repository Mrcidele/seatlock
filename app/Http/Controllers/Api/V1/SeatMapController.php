<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Booking\SeatMapBuilder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SeatMapRequest;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;

class SeatMapController extends Controller
{
    /**
     * Mapa de assentos da viagem para o trecho informado.
     */
    public function __invoke(SeatMapRequest $request, Trip $trip, SeatMapBuilder $builder): JsonResponse
    {
        $trip->load(['route.stops', 'vehicle.seats']);
        $leg = $request->legFor($trip);

        return new JsonResponse(['data' => $builder->build($trip, $leg)]);
    }
}
