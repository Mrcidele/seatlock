<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Passenger;
use App\Models\Reservation;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'trip_id' => fn (array $attributes): mixed => Order::query()->whereKey($attributes['order_id'])->value('trip_id'),
            'seat_id' => Seat::factory(),
            'passenger_id' => Passenger::factory(),
            'origin_index' => 0,
            'destination_index' => 3,
            'price_cents' => 6_000,
            'currency' => 'BRL',
            'status' => ReservationStatus::Pending,
        ];
    }
}
