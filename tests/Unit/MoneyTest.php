<?php

declare(strict_types=1);

use App\ValueObjects\Money;

it('stores money as integer cents', function (): void {
    $money = Money::of(1_999);

    expect($money->cents)->toBe(1_999)
        ->and($money->currency)->toBe('BRL')
        ->and($money->format())->toBe('R$ 19,99');
});

it('adds and subtracts in the same currency', function (): void {
    expect(Money::of(1_000)->add(Money::of(250))->cents)->toBe(1_250)
        ->and(Money::of(1_000)->subtract(Money::of(250))->cents)->toBe(750);
});

it('refuses to mix currencies', function (): void {
    Money::of(100, 'BRL')->add(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class);

it('multiplies by basis points rounding half up', function (int $cents, int $bp, int $expected): void {
    expect(Money::of($cents)->multiplyBasisPoints($bp)->cents)->toBe($expected);
})->with([
    'identity' => [1_000, 10_000, 1_000],
    'one and a half' => [1_001, 15_000, 1_502],
    '95 percent' => [12_345, 9_500, 11_728],
    'zero' => [12_345, 0, 0],
]);

it('formats thousands and negatives', function (): void {
    expect(Money::of(123_456_78)->format())->toBe('R$ 123.456,78')
        ->and(Money::of(-5)->format())->toBe('-R$ 0,05');
});
