<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\SeatStatus;
use App\Layout\CellKind;
use App\Layout\SeatLayout;
use App\Models\Seat;
use App\Models\Trip;
use App\ValueObjects\Leg;
use App\ValueObjects\Money;

/**
 * Monta o mapa de assentos de uma viagem para um trecho, pronto para o
 * front renderizar (andares > fileiras > células).
 *
 * @phpstan-type SeatPayload array{id: string, number: string, type: string, type_label: string, status: string, held_by_you: bool, price: Money}
 * @phpstan-type CellPayload array{row: int, column: int, kind: string, seat: SeatPayload|null}
 * @phpstan-type DeckPayload array{level: int, rows: int, columns: int, cells: list<list<CellPayload>>}
 * @phpstan-type SeatMap array{trip_id: string, leg: array{origin: int, destination: int, segments: list<int>}, decks: list<DeckPayload>, summary: array{available: int, locked: int, sold: int}}
 */
final readonly class SeatMapBuilder
{
    public function __construct(
        private SeatAvailability $availability,
        private FareCalculator $fares,
    ) {}

    /**
     * @param  array<string, string>  $lockOwners  seat_id => dono do lock
     * @return SeatMap
     */
    public function build(Trip $trip, Leg $leg, array $lockOwners = [], ?string $viewerOwner = null): array
    {
        $layout = SeatLayout::fromArray($trip->vehicle->layout);
        $sold = $this->availability->soldSeatIds($trip, $leg);

        /** @var array<string, Seat> $seatsByPosition */
        $seatsByPosition = [];
        foreach ($trip->vehicle->seats as $seat) {
            $seatsByPosition["{$seat->deck}:{$seat->row}:{$seat->column}"] = $seat;
        }

        $prices = [];
        $summary = ['available' => 0, 'locked' => 0, 'sold' => 0];
        $decks = [];

        foreach ($layout->decks as $deck) {
            $rows = [];

            foreach ($deck->rows as $row) {
                $cells = [];

                foreach ($row as $cell) {
                    $seat = $cell->kind === CellKind::Seat
                        ? ($seatsByPosition["{$cell->deck}:{$cell->row}:{$cell->column}"] ?? null)
                        : null;

                    $seatPayload = null;

                    if ($seat !== null) {
                        $status = match (true) {
                            isset($sold[$seat->id]) => SeatStatus::Sold,
                            isset($lockOwners[$seat->id]) => SeatStatus::Locked,
                            default => SeatStatus::Available,
                        };

                        $summary[$status->value]++;
                        $prices[$seat->type->value] ??= $this->fares->priceFor($trip, $leg, $seat->type);

                        $seatPayload = [
                            'id' => $seat->id,
                            'number' => $seat->number,
                            'type' => $seat->type->value,
                            'type_label' => $seat->type->label(),
                            'status' => $status->value,
                            'held_by_you' => $status === SeatStatus::Locked
                                && $viewerOwner !== null
                                && $lockOwners[$seat->id] === $viewerOwner,
                            'price' => $prices[$seat->type->value],
                        ];
                    }

                    $cells[] = [
                        'row' => $cell->row,
                        'column' => $cell->column,
                        'kind' => $seat === null && $cell->kind === CellKind::Seat ? CellKind::Empty->value : $cell->kind->value,
                        'seat' => $seatPayload,
                    ];
                }

                $rows[] = $cells;
            }

            $decks[] = [
                'level' => $deck->level,
                'rows' => $deck->rowCount(),
                'columns' => $deck->columns,
                'cells' => $rows,
            ];
        }

        return [
            'trip_id' => $trip->id,
            'leg' => [
                'origin' => $leg->origin,
                'destination' => $leg->destination,
                'segments' => $leg->segments(),
            ],
            'decks' => $decks,
            'summary' => $summary,
        ];
    }
}
