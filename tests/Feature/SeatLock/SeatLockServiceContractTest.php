<?php

declare(strict_types=1);

use App\SeatLock\InMemorySeatLockService;
use App\SeatLock\RedisSeatLockService;
use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;
use Illuminate\Support\Facades\Redis;

/*
 * Mesmo contrato verificado contra o Redis real e contra o fake em memória,
 * garantindo que os testes que usam o fake reflitam o comportamento real.
 */

dataset('implementations', [
    'redis' => fn (): SeatLockService => new RedisSeatLockService(app('redis'), 'locks'),
    'in-memory' => fn (): SeatLockService => new InMemorySeatLockService,
]);

beforeEach(function (): void {
    Redis::connection('locks')->flushdb();
});

it('locks all seats or none', function (SeatLockService $locks): void {
    $leg = new Leg(0, 2);

    expect($locks->acquire('trip', ['s1'], $leg, 'alice', 600)->acquired)->toBeTrue();

    $result = $locks->acquire('trip', ['s2', 's1', 's3'], $leg, 'bob', 600);

    expect($result->acquired)->toBeFalse()
        ->and($result->conflictingSeatIds)->toBe(['s1'])
        ->and($locks->owners('trip', ['s1', 's2', 's3'], $leg))->toBe(['s1' => 'alice']);
})->with('implementations');

it('uses the documented key format', function (): void {
    (new RedisSeatLockService(app('redis'), 'locks'))->acquire('T1', ['S1'], new Leg(1, 3), 'owner-token', 600);

    $redis = Redis::connection('locks');

    expect($redis->get('lock:{T1}:S1:1'))->toBe('owner-token')
        ->and($redis->get('lock:{T1}:S1:2'))->toBe('owner-token')
        ->and($redis->ttl('lock:{T1}:S1:1'))->toBeGreaterThan(590)->toBeLessThanOrEqual(600);
});

it('lets different owners hold the same seat on disjoint segments', function (SeatLockService $locks): void {
    expect($locks->acquire('trip', ['s1'], new Leg(0, 1), 'alice', 600)->acquired)->toBeTrue()
        ->and($locks->acquire('trip', ['s1'], new Leg(1, 3), 'bob', 600)->acquired)->toBeTrue()
        ->and($locks->acquire('trip', ['s1'], new Leg(0, 2), 'carol', 600)->conflictingSeatIds)->toBe(['s1']);
})->with('implementations');

it('is idempotent for the same owner without extending the ttl', function (SeatLockService $locks): void {
    $first = $locks->acquire('trip', ['s1'], new Leg(0, 1), 'alice', 600);
    $second = $locks->acquire('trip', ['s1', 's2'], new Leg(0, 1), 'alice', 900);

    expect($second->acquired)->toBeTrue()
        ->and($second->expiresAt?->lessThanOrEqualTo($first->expiresAt?->addSecond()))->toBeTrue();
})->with('implementations');

it('releases only locks owned by the caller', function (SeatLockService $locks): void {
    $leg = new Leg(0, 2);
    $locks->acquire('trip', ['s1'], $leg, 'alice', 600);

    expect($locks->release('trip', ['s1'], $leg, 'mallory'))->toBe(0)
        ->and($locks->owners('trip', ['s1'], $leg))->toBe(['s1' => 'alice'])
        ->and($locks->release('trip', ['s1'], $leg, 'alice'))->toBe(2)
        ->and($locks->owners('trip', ['s1'], $leg))->toBe([]);
})->with('implementations');

it('renews only when the owner still holds every key', function (SeatLockService $locks): void {
    $leg = new Leg(0, 2);
    $locks->acquire('trip', ['s1', 's2'], $leg, 'alice', 60);

    expect($locks->renew('trip', ['s1', 's2'], $leg, 'mallory', 600)->acquired)->toBeFalse();

    $renewed = $locks->renew('trip', ['s1', 's2'], $leg, 'alice', 600);

    expect($renewed->acquired)->toBeTrue()
        ->and($locks->heldUntil('trip', ['s1', 's2'], $leg, 'alice')?->diffInSeconds(now()->addSeconds(600)))->toBeLessThan(2);

    $locks->release('trip', ['s2'], $leg, 'alice');

    expect($locks->renew('trip', ['s1', 's2'], $leg, 'alice', 600)->acquired)->toBeFalse();
})->with('implementations');

it('reports when the owner does not hold all requested seats', function (SeatLockService $locks): void {
    $leg = new Leg(0, 1);
    $locks->acquire('trip', ['s1'], $leg, 'alice', 600);

    expect($locks->heldUntil('trip', ['s1'], $leg, 'alice'))->not->toBeNull()
        ->and($locks->heldUntil('trip', ['s1', 's2'], $leg, 'alice'))->toBeNull()
        ->and($locks->heldUntil('trip', ['s1'], $leg, 'bob'))->toBeNull();
})->with('implementations');

it('expires locks after the ttl in redis', function (): void {
    $locks = new RedisSeatLockService(app('redis'), 'locks');
    $locks->acquire('trip', ['s1'], new Leg(0, 1), 'alice', 1);

    usleep(1_100_000);

    expect($locks->owners('trip', ['s1'], new Leg(0, 1)))->toBe([])
        ->and($locks->acquire('trip', ['s1'], new Leg(0, 1), 'bob', 600)->acquired)->toBeTrue();
});

it('expires locks after the ttl in memory', function (): void {
    $locks = new InMemorySeatLockService;
    $locks->acquire('trip', ['s1'], new Leg(0, 1), 'alice', 600);

    $this->travel(601)->seconds();

    expect($locks->acquire('trip', ['s1'], new Leg(0, 1), 'bob', 600)->acquired)->toBeTrue();
});
