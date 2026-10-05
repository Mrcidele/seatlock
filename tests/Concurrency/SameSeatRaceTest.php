<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\SeatSegment;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/*
 * Processos PHP reais e independentes disputando o mesmo assento. Os dados
 * precisam estar commitados (sem RefreshDatabase) para os filhos enxergarem.
 */
uses(TestCase::class, DatabaseTruncation::class);

const CONTENDERS = 12;

/**
 * @param  list<array{user: User, origin?: int, destination?: int}>  $buyers
 * @return list<string> resultado de cada processo
 */
function race(Trip $trip, string $seatId, array $buyers, bool $withoutRedisLock = false): array
{
    $startAt = microtime(true) + 2.0;

    $results = Process::pool(function (Pool $pool) use ($trip, $seatId, $buyers, $startAt, $withoutRedisLock): void {
        foreach ($buyers as $i => $buyer) {
            $command = [
                PHP_BINARY, 'artisan', 'seatlock:simulate-purchase',
                $trip->id, $seatId, $buyer['user']->id,
                '--origin='.($buyer['origin'] ?? 0),
                '--destination='.($buyer['destination'] ?? 3),
                '--cart=race-cart-'.$i,
                '--start-at='.$startAt,
            ];

            if ($withoutRedisLock) {
                $command[] = '--without-redis-lock';
            }

            $pool->path(base_path())->timeout(60)->command($command);
        }
    })->start()->wait();

    $outcomes = [];

    foreach ($results as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput());
        $decoded = json_decode(trim($result->output()), true);
        $outcomes[] = is_array($decoded) && is_string($decoded['result'] ?? null) ? $decoded['result'] : 'invalid:'.$result->output();
    }

    return $outcomes;
}

/**
 * Nenhum (viagem, assento, segmento) pode aparecer duas vezes.
 */
function assertNoDoubleBooking(): void
{
    $duplicates = DB::table('seat_segments')
        ->select('trip_id', 'seat_id', 'segment_index')
        ->groupBy('trip_id', 'seat_id', 'segment_index')
        ->havingRaw('count(*) > 1')
        ->count();

    expect($duplicates)->toBe(0);
}

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    $this->seat = seatNumbered($this->trip, '01');
    $this->buyers = User::factory()->count(CONTENDERS)->create()->map(fn (User $user): array => ['user' => $user])->all();
});

it('sells the seat to exactly one of many parallel buyers', function (): void {
    $outcomes = race($this->trip, $this->seat->id, $this->buyers);

    expect(array_count_values($outcomes)['confirmed'] ?? 0)->toBe(1)
        ->and(count($outcomes))->toBe(CONTENDERS)
        ->and(SeatSegment::query()->where('seat_id', $this->seat->id)->count())->toBe(3)
        ->and(Order::query()->where('status', OrderStatus::Paid)->count())->toBe(1);

    // Com o Redis ativo, os perdedores são barrados antes de pagar (no lock ou
    // na criação do pedido, se chegaram depois da venda): ninguém paga à toa.
    $counts = array_count_values($outcomes);
    expect(($counts['lock_conflict'] ?? 0) + ($counts['order_rejected'] ?? 0))->toBe(CONTENDERS - 1)
        ->and($counts['seats_taken'] ?? 0)->toBe(0);

    assertNoDoubleBooking();
});

it('still sells the seat exactly once when redis is unavailable', function (): void {
    $outcomes = race($this->trip, $this->seat->id, $this->buyers, withoutRedisLock: true);
    $counts = array_count_values($outcomes);

    // Sem lock, quem chega antes da venda vai até o pagamento e é barrado pelo
    // UNIQUE do banco (seats_taken => estorno); quem chega depois vê o assento
    // vendido na checagem do banco.
    expect($counts['confirmed'] ?? 0)->toBe(1)
        ->and(($counts['seats_taken'] ?? 0) + ($counts['order_rejected'] ?? 0) + ($counts['lock_conflict'] ?? 0))->toBe(CONTENDERS - 1)
        ->and(Order::query()->where('status', OrderStatus::Cancelled)->where('cancellation_reason', 'seat_unavailable')->count())->toBe($counts['seats_taken'] ?? 0)
        ->and(SeatSegment::query()->where('seat_id', $this->seat->id)->count())->toBe(3)
        ->and(Order::query()->where('status', OrderStatus::Paid)->count())->toBe(1)
        ->and(Order::query()->where('status', OrderStatus::Pending)->count())->toBe(0);

    assertNoDoubleBooking();
});

it('never double books a segment when buyers race on overlapping legs', function (bool $withoutRedisLock): void {
    $legs = [[0, 1], [1, 2], [2, 3], [0, 2], [1, 3], [0, 3]];
    $buyers = [];

    foreach ($this->buyers as $i => $buyer) {
        [$origin, $destination] = $legs[$i % count($legs)];
        $buyers[] = [...$buyer, 'origin' => $origin, 'destination' => $destination];
    }

    $outcomes = race($this->trip, $this->seat->id, $buyers, $withoutRedisLock);

    expect($outcomes)->toContain('confirmed');
    assertNoDoubleBooking();

    // Os trechos vendidos não se sobrepõem e cobrem no máximo os 3 segmentos.
    $paid = Order::query()->where('status', OrderStatus::Paid)->get();
    $segments = $paid->flatMap(fn (Order $order): array => $order->leg()->segments())->all();

    expect(count($segments))->toBe(count(array_unique($segments)))
        ->and(SeatSegment::query()->count())->toBe(count($segments));
})->with(['redis lock' => false, 'redis down' => true]);
