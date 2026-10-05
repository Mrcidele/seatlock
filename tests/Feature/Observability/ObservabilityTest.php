<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Livewire\Pulse\BookingMetricsCard;
use App\Models\Order;
use App\Models\User;
use App\Observability\BookingMetrics;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('tags every response with a request id and keeps a valid incoming one', function (): void {
    $generated = $this->getJson('/api/v1/trips')->assertOk()->headers->get('X-Request-Id');
    expect($generated)->toMatch('/^[0-9a-f-]{36}$/');

    $this->getJson('/api/v1/trips', ['X-Request-Id' => 'edge-req-12345'])->assertHeader('X-Request-Id', 'edge-req-12345');
});

it('reports healthy when the database answers', function (): void {
    $this->get('/up')->assertOk();
});

it('computes business metrics from the database for admins only', function (): void {
    Order::factory()->create(['created_at' => now()->subMinutes(30)]);
    Order::factory()->status(OrderStatus::Expired)->create();
    Order::factory()->status(OrderStatus::Cancelled)->create(['cancellation_reason' => 'seat_unavailable']);
    Order::factory()->status(OrderStatus::Paid)->create(['created_at' => now()->subMinutes(4), 'paid_at' => now()->subMinutes(2)]);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/admin/metrics')->assertForbidden();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->getJson('/api/v1/admin/metrics?hours=24')
        ->assertOk()
        ->assertJsonPath('data.orders_created', 4)
        ->assertJsonPath('data.orders_paid', 1)
        ->assertJsonPath('data.orders_expired', 1)
        ->assertJsonPath('data.orders_lost_to_conflict', 1)
        ->assertJsonPath('data.conversion_rate', 0.25)
        ->assertJsonPath('data.avg_seconds_to_pay', 120);
});

it('renders the booking metrics card on Pulse', function (): void {
    config()->set('pulse.enabled', true);
    Pulse::startRecording();

    BookingMetrics::lockAcquired(2);
    BookingMetrics::lockAcquired(1);
    BookingMetrics::orderCreated();
    BookingMetrics::orderPaid(Order::factory()->status(OrderStatus::Paid)->create(['created_at' => now()->subSeconds(90), 'paid_at' => now()]));
    Pulse::ingest();

    Livewire::test(BookingMetricsCard::class, ['lazy' => false])
        ->assertSee('Reservas')
        ->assertSee('50%')
        ->assertSee('1m30s');
});
