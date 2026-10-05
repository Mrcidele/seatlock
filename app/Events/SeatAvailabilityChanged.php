<?php

declare(strict_types=1);

namespace App\Events;

use App\ValueObjects\Leg;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base dos eventos do canal público trip.{id}. Só é transmitido depois do
 * commit, para o mapa nunca mostrar um estado que o banco ainda pode desfazer.
 * Não inclui o dono do lock (token do carrinho).
 */
abstract class SeatAvailabilityChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $seatIds
     */
    final public function __construct(
        public readonly string $tripId,
        public readonly array $seatIds,
        public readonly int $origin,
        public readonly int $destination,
    ) {}

    /**
     * @param  list<string>  $seatIds
     */
    public static function forLeg(string $tripId, array $seatIds, Leg $leg): static
    {
        return new static($tripId, array_values($seatIds), $leg->origin, $leg->destination);
    }

    public function broadcastOn(): Channel
    {
        return new Channel("trip.{$this->tripId}");
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /**
     * @return array{trip_id: string, seat_ids: list<string>, origin: int, destination: int, segments: list<int>}
     */
    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'seat_ids' => $this->seatIds,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'segments' => range($this->origin, $this->destination - 1),
        ];
    }

    public function broadcastAs(): string
    {
        return class_basename(static::class);
    }
}
