<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Stop;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trip
 */
class TripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'departure_at' => $this->departure_at->toIso8601String(),
            'route' => [
                'id' => $this->route->id,
                'code' => $this->route->code,
                'name' => $this->route->name,
            ],
            'vehicle' => [
                'id' => $this->vehicle->id,
                'name' => $this->vehicle->name,
            ],
            'stops' => $this->stops()->map(fn (Stop $stop): array => [
                'index' => $stop->sequence,
                'name' => $stop->name,
                'city' => $stop->city,
                'state' => $stop->state,
                'departure_at' => $this->departure_at->addMinutes($stop->minutes_from_origin)->toIso8601String(),
                'fare_from_origin' => $stop->fare_from_origin,
            ])->values()->all(),
        ];
    }
}
