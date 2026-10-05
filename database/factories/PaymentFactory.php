<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'fake',
            'method' => PaymentMethod::Pix,
            'status' => PaymentStatus::Pending,
            'amount_cents' => 6_000,
            'refunded_cents' => 0,
            'currency' => 'BRL',
            'external_id' => 'fake_'.Str::lower((string) Str::ulid()),
        ];
    }
}
