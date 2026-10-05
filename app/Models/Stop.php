<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Database\Factories\StopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $route_id
 * @property int $sequence
 * @property string $name
 * @property string $city
 * @property string $state
 * @property int $minutes_from_origin
 * @property int $fare_from_origin_cents
 * @property-read Money $fare_from_origin
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read TravelRoute $route
 */
#[Fillable(['route_id', 'sequence', 'name', 'city', 'state', 'minutes_from_origin', 'fare_from_origin_cents'])]
class Stop extends Model
{
    /** @use HasFactory<StopFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return BelongsTo<TravelRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelRoute::class, 'route_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'minutes_from_origin' => 'integer',
            'fare_from_origin_cents' => 'integer',
            'fare_from_origin' => MoneyCast::class.':fare_from_origin_cents,currency',
        ];
    }
}
