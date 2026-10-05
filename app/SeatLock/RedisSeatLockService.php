<?php

declare(strict_types=1);

namespace App\SeatLock;

use App\ValueObjects\Leg;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;

final readonly class RedisSeatLockService implements SeatLockService
{
    /**
     * Verifica todas as chaves antes de gravar qualquer uma: ou trava tudo,
     * ou devolve os índices (1-based) das chaves em conflito.
     * Retorno: {1, menor_pttl_ms} | {0, idx, idx, ...}
     */
    private const string ACQUIRE = <<<'LUA'
        local owner = ARGV[1]
        local ttl = tonumber(ARGV[2])
        local conflicts = {}
        for i, key in ipairs(KEYS) do
            local current = redis.call('GET', key)
            if current and current ~= owner then
                conflicts[#conflicts + 1] = i
            end
        end
        if #conflicts > 0 then
            return {0, unpack(conflicts)}
        end
        local minTtl = ttl * 1000
        for _, key in ipairs(KEYS) do
            if not redis.call('SET', key, owner, 'EX', ttl, 'NX') then
                local pttl = redis.call('PTTL', key)
                if pttl > 0 and pttl < minTtl then
                    minTtl = pttl
                end
            end
        end
        return {1, minTtl}
        LUA;

    /** Apaga somente as chaves cujo valor é o token do dono. */
    private const string RELEASE = <<<'LUA'
        local released = 0
        for _, key in ipairs(KEYS) do
            if redis.call('GET', key) == ARGV[1] then
                redis.call('DEL', key)
                released = released + 1
            end
        end
        return released
        LUA;

    /** Renova o TTL apenas se o dono detiver todas as chaves. */
    private const string RENEW = <<<'LUA'
        for _, key in ipairs(KEYS) do
            if redis.call('GET', key) ~= ARGV[1] then
                return 0
            end
        end
        for _, key in ipairs(KEYS) do
            redis.call('EXPIRE', key, ARGV[2])
        end
        return 1
        LUA;

    /** Menor PTTL (ms) se o dono detiver todas as chaves; -1 caso contrário. */
    private const string HELD_UNTIL = <<<'LUA'
        local minTtl = -1
        for _, key in ipairs(KEYS) do
            if redis.call('GET', key) ~= ARGV[1] then
                return -1
            end
            local pttl = redis.call('PTTL', key)
            if minTtl == -1 or pttl < minTtl then
                minTtl = pttl
            end
        end
        return minTtl
        LUA;

    public function __construct(
        private RedisFactory $redis,
        private string $connection = 'locks',
    ) {}

    public function acquire(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult
    {
        $keys = LockKeys::forSeats($tripId, $seatIds, $leg);
        $reply = $this->eval(self::ACQUIRE, array_column($keys, 'key'), [$owner, $ttlSeconds]);

        if (! is_array($reply) || $reply === []) {
            throw new LockBackendUnavailable('Resposta inesperada do Redis ao travar assentos.');
        }

        $status = (int) array_shift($reply);

        if ($status === 1) {
            $ttlMs = (int) ($reply[0] ?? $ttlSeconds * 1000);

            return LockResult::acquired($this->expiryFromMilliseconds($ttlMs));
        }

        $conflicting = [];
        foreach ($reply as $index) {
            $conflicting[] = $keys[(int) $index - 1]['seat_id'];
        }

        return LockResult::conflict($conflicting);
    }

    public function release(string $tripId, array $seatIds, Leg $leg, string $owner): int
    {
        if ($seatIds === []) {
            return 0;
        }

        $keys = array_column(LockKeys::forSeats($tripId, $seatIds, $leg), 'key');

        return (int) $this->eval(self::RELEASE, $keys, [$owner]);
    }

    public function renew(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult
    {
        $keys = array_column(LockKeys::forSeats($tripId, $seatIds, $leg), 'key');
        $renewed = (int) $this->eval(self::RENEW, $keys, [$owner, $ttlSeconds]);

        return $renewed === 1
            ? LockResult::acquired(CarbonImmutable::now()->addSeconds($ttlSeconds))
            : LockResult::notHeld();
    }

    public function heldUntil(string $tripId, array $seatIds, Leg $leg, string $owner): ?CarbonImmutable
    {
        if ($seatIds === []) {
            return null;
        }

        $keys = array_column(LockKeys::forSeats($tripId, $seatIds, $leg), 'key');
        $ttlMs = (int) $this->eval(self::HELD_UNTIL, $keys, [$owner]);

        return $ttlMs > 0 ? $this->expiryFromMilliseconds($ttlMs) : null;
    }

    public function owners(string $tripId, array $seatIds, Leg $leg): array
    {
        if ($seatIds === []) {
            return [];
        }

        $keys = LockKeys::forSeats($tripId, $seatIds, $leg);
        $values = $this->client()->command('mget', [array_column($keys, 'key')]);

        if (! is_array($values)) {
            throw new LockBackendUnavailable('Resposta inesperada do Redis ao consultar locks.');
        }

        $owners = [];
        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? false;

            if (is_string($value) && ! isset($owners[$key['seat_id']])) {
                $owners[$key['seat_id']] = $value;
            }
        }

        return $owners;
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string|int>  $arguments
     */
    private function eval(string $script, array $keys, array $arguments): mixed
    {
        $result = $this->client()->eval($script, count($keys), ...$keys, ...$arguments);

        if ($result === false) {
            throw new LockBackendUnavailable('Falha ao executar script de lock no Redis.');
        }

        return $result;
    }

    private function client(): PhpRedisConnection
    {
        $connection = $this->redis->connection($this->connection);

        if (! $connection instanceof PhpRedisConnection) {
            throw new LockBackendUnavailable('O SeatLockService requer o cliente phpredis.');
        }

        return $connection;
    }

    private function expiryFromMilliseconds(int $milliseconds): CarbonImmutable
    {
        return CarbonImmutable::now()->addMilliseconds($milliseconds);
    }
}
