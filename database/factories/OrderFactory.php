<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trip_id' => Trip::factory(),
            'origin_index' => 0,
            'destination_index' => 3,
            'status' => OrderStatus::Pending,
            'total_cents' => 6_000,
            'refunded_cents' => 0,
            'currency' => 'BRL',
            'lock_owner' => (string) Str::ulid(),
            'lock_renewals' => 0,
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
