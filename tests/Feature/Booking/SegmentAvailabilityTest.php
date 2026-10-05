<?php

declare(strict_types=1);

use App\Booking\SeatAvailability;
use App\Models\Reservation;
use App\Models\SeatSegment;
use App\Models\Trip;
use App\ValueObjects\Leg;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function (): void {
    // Paradas A(0) B(1) C(2) D(3) => segmentos 0, 1, 2.
    $this->trip = Trip::factory()->create();
    $this->seat = seatNumbered($this->trip, '01');
    $this->availability = app(SeatAvailability::class);
});

it('sells the same seat for consecutive legs A->B and B->C', function (): void {
    sellSeat($this->trip, $this->seat, new Leg(0, 1));

    expect($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(1, 2)))->toBeTrue()
        ->and($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(1, 3)))->toBeTrue();

    sellSeat($this->trip, $this->seat, new Leg(1, 2));

    expect($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(2, 3)))->toBeTrue();
});

it('considers a seat taken for A->C if any segment between A and C is taken', function (): void {
    sellSeat($this->trip, $this->seat, new Leg(1, 2));

    expect($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(0, 2)))->toBeFalse()
        ->and($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(0, 3)))->toBeFalse()
        ->and($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(0, 1)))->toBeTrue()
        ->and($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(2, 3)))->toBeTrue();
});

it('lists only seats free on every segment of the leg', function (): void {
    sellSeat($this->trip, $this->seat, new Leg(2, 3));

    expect($this->availability->availableSeats($this->trip, new Leg(0, 2)))->toHaveCount(12)
        ->and($this->availability->availableSeats($this->trip, new Leg(0, 3)))->toHaveCount(11);
});

it('does not mix up trips', function (): void {
    $otherTrip = Trip::factory()->create(['route_id' => $this->trip->route_id, 'vehicle_id' => $this->trip->vehicle_id]);
    sellSeat($otherTrip, $this->seat, new Leg(0, 3));

    expect($this->availability->isAvailable($this->trip, $this->seat->id, new Leg(0, 3)))->toBeTrue();
});

it('enforces UNIQUE(trip_id, seat_id, segment_index) in PostgreSQL', function (): void {
    $first = sellSeat($this->trip, $this->seat, new Leg(0, 2));
    $second = Reservation::factory()->create([
        'order_id' => $first->order_id,
        'seat_id' => seatNumbered($this->trip, '02')->id,
    ]);

    SeatSegment::query()->insert([
        'trip_id' => $this->trip->id,
        'seat_id' => $this->seat->id,
        'segment_index' => 1,
        'reservation_id' => $second->id,
    ]);
})->throws(UniqueConstraintViolationException::class);
