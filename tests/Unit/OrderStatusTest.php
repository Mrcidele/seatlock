<?php

declare(strict_types=1);

use App\Enums\OrderStatus;

it('allows only the documented transitions', function (OrderStatus $from, OrderStatus $to, bool $allowed): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [OrderStatus::Pending, OrderStatus::Paid, true],
    [OrderStatus::Pending, OrderStatus::Expired, true],
    [OrderStatus::Pending, OrderStatus::Cancelled, true],
    [OrderStatus::Pending, OrderStatus::Refunded, false],
    [OrderStatus::Expired, OrderStatus::Paid, true],
    [OrderStatus::Expired, OrderStatus::Cancelled, false],
    [OrderStatus::Paid, OrderStatus::Refunded, true],
    [OrderStatus::Paid, OrderStatus::Cancelled, true],
    [OrderStatus::Paid, OrderStatus::Pending, false],
    [OrderStatus::Paid, OrderStatus::Expired, false],
    [OrderStatus::Refunded, OrderStatus::Paid, false],
    [OrderStatus::Cancelled, OrderStatus::Paid, false],
]);

it('marks terminal states as final', function (): void {
    expect(OrderStatus::Cancelled->isFinal())->toBeTrue()
        ->and(OrderStatus::Refunded->isFinal())->toBeTrue()
        ->and(OrderStatus::Pending->isFinal())->toBeFalse();
});
