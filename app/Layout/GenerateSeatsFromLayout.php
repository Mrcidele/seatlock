<?php

declare(strict_types=1);

namespace App\Layout;

use App\Models\Reservation;
use App\Models\Seat;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Cria os registros de seats de um veículo a partir do layout JSON.
 */
final readonly class GenerateSeatsFromLayout
{
    /**
     * @return int quantidade de assentos gerados
     */
    public function handle(Vehicle $vehicle): int
    {
        $layout = SeatLayout::fromArray($vehicle->layout);

        return DB::transaction(function () use ($vehicle, $layout): int {
            $hasReservations = Reservation::query()
                ->whereIn('seat_id', $vehicle->seats()->select('id'))
                ->exists();

            if ($hasReservations) {
                throw new LogicException("O veículo {$vehicle->plate} já tem reservas; o layout não pode ser regenerado.");
            }

            $vehicle->seats()->delete();

            foreach ($layout->seats() as $cell) {
                Seat::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'number' => $cell->seatNumber,
                    'deck' => $cell->deck,
                    'row' => $cell->row,
                    'column' => $cell->column,
                    'type' => $cell->seatType,
                ]);
            }

            return $layout->seatCount();
        });
    }
}
