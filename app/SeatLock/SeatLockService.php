<?php

declare(strict_types=1);

namespace App\SeatLock;

use App\ValueObjects\Leg;
use Carbon\CarbonImmutable;

/**
 * Lock temporário de assentos por segmento. É uma otimização de UX (reduz
 * conflitos enquanto o cliente paga); a garantia final contra venda dupla é
 * o UNIQUE de seat_segments no PostgreSQL.
 *
 * Cada (viagem, assento, segmento) é uma chave; o valor é o token do dono
 * (o carrinho). Só o dono consegue renovar ou liberar.
 */
interface SeatLockService
{
    /**
     * Trava todos os assentos em todos os segmentos do trecho, ou nenhum.
     * Se o dono já tiver parte das chaves, elas são mantidas com o TTL
     * original (re-travar não estende o prazo).
     *
     * @param  list<string>  $seatIds
     */
    public function acquire(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult;

    /**
     * Libera apenas as chaves que pertencem ao dono.
     *
     * @param  list<string>  $seatIds
     * @return int quantidade de chaves liberadas
     */
    public function release(string $tripId, array $seatIds, Leg $leg, string $owner): int;

    /**
     * Estende o TTL somente se o dono ainda detiver todas as chaves.
     *
     * @param  list<string>  $seatIds
     */
    public function renew(string $tripId, array $seatIds, Leg $leg, string $owner, int $ttlSeconds): LockResult;

    /**
     * Momento em que o primeiro lock do dono expira, ou null se ele não
     * detiver todas as chaves.
     *
     * @param  list<string>  $seatIds
     */
    public function heldUntil(string $tripId, array $seatIds, Leg $leg, string $owner): ?CarbonImmutable;

    /**
     * Donos dos locks ativos em qualquer segmento do trecho.
     *
     * @param  list<string>  $seatIds
     * @return array<string, string> seat_id => dono
     */
    public function owners(string $tripId, array $seatIds, Leg $leg): array;
}
