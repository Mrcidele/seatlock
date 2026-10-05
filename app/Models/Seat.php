<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeatType;
use Carbon\CarbonImmutable;
use Database\Factories\SeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $vehicle_id
 * @property string $number
 * @property int $deck
 * @property int $row
 * @property int $column
 * @property SeatType $type
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Vehicle $vehicle
 */
#[Fillable(['vehicle_id', 'number', 'deck', 'row', 'column', 'type'])]
class Seat extends Model
{
    /** @use HasFactory<SeatFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deck' => 'integer',
            'row' => 'integer',
            'column' => 'integer',
            'type' => SeatType::class,
        ];
    }
}
