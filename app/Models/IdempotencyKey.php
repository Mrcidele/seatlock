<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $scope
 * @property string $key
 * @property string $method
 * @property string $path
 * @property string $request_hash
 * @property int|null $response_status
 * @property array<string, string>|null $response_headers
 * @property string|null $response_body
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable([
    'scope', 'key', 'method', 'path', 'request_hash', 'response_status', 'response_headers', 'response_body', 'completed_at',
])]
class IdempotencyKey extends Model
{
    use HasUlids;
    use MassPrunable;

    /**
     * Chaves ficam guardadas por 24 h, tempo de sobra para retries do cliente.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDay());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_headers' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
