<?php

declare(strict_types=1);

use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;

it('keeps working in degraded mode when redis is down', function (): void {
    simulateRedisOutage();

    $locks = app(SeatLockService::class);
    $result = $locks->acquire('trip', ['s1'], new Leg(0, 1), 'alice', 600);

    expect($result->acquired)->toBeTrue()
        ->and($result->degraded)->toBeTrue()
        ->and($locks->owners('trip', ['s1'], new Leg(0, 1)))->toBe([])
        ->and($locks->heldUntil('trip', ['s1'], new Leg(0, 1), 'alice'))->not->toBeNull()
        ->and($locks->release('trip', ['s1'], new Leg(0, 1), 'alice'))->toBe(0);
});
