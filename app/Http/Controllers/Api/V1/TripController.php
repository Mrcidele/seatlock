<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Booking\TripSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchTripsRequest;
use App\Http\Resources\Api\V1\TripResource;
use App\Http\Resources\Api\V1\TripSearchResultResource;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripController extends Controller
{
    /**
     * Busca viagens por cidade de origem/destino e data.
     */
    public function index(SearchTripsRequest $request, TripSearch $search): AnonymousResourceCollection
    {
        $date = $request->filled('date') ? CarbonImmutable::createFromFormat('Y-m-d', $request->string('date')->toString()) : null;

        return TripSearchResultResource::collection($search->search(
            $request->filled('origin') ? $request->string('origin')->toString() : null,
            $request->filled('destination') ? $request->string('destination')->toString() : null,
            $date,
        ));
    }

    public function show(Trip $trip): TripResource
    {
        $trip->load(['route.stops', 'vehicle']);

        return new TripResource($trip);
    }
}
