<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TravelRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Linha rodoviária (tabela routes). Chamada de TravelRoute para não colidir
 * com o facade Route.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, Stop> $stops
 */
#[Table('routes')]
#[Fillable(['code', 'name'])]
class TravelRoute extends Model
{
    /** @use HasFactory<TravelRouteFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return HasMany<Stop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class, 'route_id')->orderBy('sequence');
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'route_id');
    }
}
