<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;

it('stamps the moment of each transition', function (): void {
    $order = Order::factory()->create();

    $order->transitionTo(OrderStatus::Paid);

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();
});

it('throws on invalid transitions and keeps the state untouched', function (OrderStatus $from, OrderStatus $to): void {
    $order = Order::factory()->status($from)->create();

    expect(fn () => $order->transitionTo($to))->toThrow(InvalidOrderTransition::class)
        ->and($order->refresh()->status)->toBe($from);
})->with([
    [OrderStatus::Pending, OrderStatus::Refunded],
    [OrderStatus::Paid, OrderStatus::Expired],
    [OrderStatus::Cancelled, OrderStatus::Paid],
    [OrderStatus::Refunded, OrderStatus::Cancelled],
]);

it('rejects operations not allowed in the current state with 409 problems', function (): void {
    customer();
    $order = placeOrder(App\Models\Trip::factory()->create(), ['01']);
    Order::query()->whereKey($order['id'])->update(['status' => OrderStatus::Expired]);

    $this->postJson("/api/v1/orders/{$order['id']}/cancel")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');
});
