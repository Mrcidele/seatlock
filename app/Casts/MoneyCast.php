<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Converte um par de colunas (centavos + moeda) em Money.
 *
 * Uso: 'total' => MoneyCast::class.':total_cents,currency'
 *
 * @implements CastsAttributes<Money, Money>
 */
final readonly class MoneyCast implements CastsAttributes
{
    public function __construct(
        private string $centsColumn,
        private string $currencyColumn = 'currency',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Money
    {
        $cents = $attributes[$this->centsColumn] ?? 0;
        $currency = $attributes[$this->currencyColumn] ?? config('seatlock.currency');

        return new Money(
            is_numeric($cents) ? (int) $cents : 0,
            is_string($currency) ? $currency : 'BRL',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof Money) {
            throw new InvalidArgumentException("{$key} precisa ser uma instância de Money.");
        }

        return [
            $this->centsColumn => $value->cents,
            $this->currencyColumn => $value->currency,
        ];
    }
}
