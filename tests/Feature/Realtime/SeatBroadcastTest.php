<?php

declare(strict_types=1);

use App\Booking\Actions\ConfirmOrder;
use App\Events\SeatLocked;
use App\Events\SeatReleased;
use App\Events\SeatSold;
use App\Models\Trip;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    $this->seat = seatNumbered($this->trip, '01');
    customer();
});

it('broadcasts SeatLocked on the trip channel without leaking the cart token', function (): void {
    Event::fake([SeatLocked::class]);

    $this->postJson("/api/v1/trips/{$this->trip->id}/locks", ['origin' => 1, 'destination' => 3, 'seat_ids' => [$this->seat->id]], cartHeaders())->assertCreated();

    Event::assertDispatched(SeatLocked::class, function (SeatLocked $event): bool {
        expect($event->broadcastOn()->name)->toBe("trip.{$this->trip->id}")
            ->and($event->broadcastAs())->toBe('SeatLocked')
            ->and($event->broadcastWith())->toBe([
                'trip_id' => $this->trip->id,
                'seat_ids' => [$this->seat->id],
                'origin' => 1,
                'destination' => 3,
                'segments' => [1, 2],
            ]);

        return true;
    });
});

it('broadcasts SeatReleased when the cart releases or the order is cancelled', function (): void {
    Event::fake([SeatReleased::class]);

    $this->postJson("/api/v1/trips/{$this->trip->id}/locks", ['origin' => 0, 'destination' => 3, 'seat_ids' => [$this->seat->id]], cartHeaders());
    $this->deleteJson("/api/v1/trips/{$this->trip->id}/locks", ['origin' => 0, 'destination' => 3, 'seat_ids' => [$this->seat->id]], cartHeaders());
    Event::assertDispatchedTimes(SeatReleased::class, 1);

    // Liberar o que não é seu não gera evento.
    $this->deleteJson("/api/v1/trips/{$this->trip->id}/locks", ['origin' => 0, 'destination' => 3, 'seat_ids' => [$this->seat->id]], cartHeaders());
    Event::assertDispatchedTimes(SeatReleased::class, 1);

    $order = placeOrder($this->trip, ['02']);
    $this->postJson("/api/v1/orders/{$order['id']}/cancel")->assertOk();
    Event::assertDispatchedTimes(SeatReleased::class, 2);
});

it('broadcasts SeatSold when the order is confirmed', function (): void {
    Event::fake([SeatSold::class]);
    $order = placeOrder($this->trip, ['01']);

    app(ConfirmOrder::class)->handle($order['id']);
    app(ConfirmOrder::class)->handle($order['id']);

    Event::assertDispatchedTimes(SeatSold::class, 1);
    Event::assertDispatched(SeatSold::class, fn (SeatSold $e): bool => $e->seatIds === [$this->seat->id]);
});

it('broadcasts only after the transaction commits', function (): void {
    expect(new SeatSold('t', [], 0, 1))->toBeInstanceOf(Illuminate\Contracts\Events\ShouldDispatchAfterCommit::class);
});
