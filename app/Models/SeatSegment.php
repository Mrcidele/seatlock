<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ocupação de um assento num segmento da viagem. O UNIQUE(trip_id, seat_id,
 * segment_index) é a garantia definitiva contra venda dupla.
 *
 * @property string $trip_id
 * @property string $seat_id
 * @property int $segment_index
 * @property string $reservation_id
 * @property CarbonImmutable $created_at
 * @property-read Reservation $reservation
 */
#[Fillable(['trip_id', 'seat_id', 'segment_index', 'reservation_id'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
class SeatSegment extends Model
{
    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment_index' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
