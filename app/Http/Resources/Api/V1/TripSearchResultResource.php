<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Booking\TripSearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TripSearchResult $resource
 */
class TripSearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new TripResource($this->resource->trip))->toArray($request),
            'leg' => [
                'origin' => $this->resource->leg->origin,
                'destination' => $this->resource->leg->destination,
            ],
            'available_seats' => $this->resource->availableSeats,
            'from_price' => $this->resource->fromPrice,
        ];
    }
}
