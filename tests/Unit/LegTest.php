<?php

declare(strict_types=1);

use App\ValueObjects\Leg;

it('lists the segments covered by a leg', function (): void {
    expect((new Leg(0, 3))->segments())->toBe([0, 1, 2])
        ->and((new Leg(1, 2))->segments())->toBe([1]);
});

it('detects overlapping legs', function (): void {
    expect((new Leg(0, 1))->overlaps(new Leg(1, 2)))->toBeFalse()
        ->and((new Leg(0, 2))->overlaps(new Leg(1, 3)))->toBeTrue();
});

it('rejects invalid legs', function (int $origin, int $destination): void {
    new Leg($origin, $destination);
})->with([[1, 1], [2, 1], [-1, 2]])->throws(InvalidArgumentException::class);
