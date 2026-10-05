<?php

declare(strict_types=1);

use App\Enums\SeatType;
use App\Layout\GenerateSeatsFromLayout;
use App\Layout\LayoutPresets;
use App\Models\Seat;
use App\Models\Vehicle;
use Database\Seeders\FleetSeeder;

it('generates seats from the vehicle layout', function (): void {
    $this->seed(FleetSeeder::class);

    $doubleDecker = Vehicle::query()->where('plate', 'SLK1A23')->firstOrFail();

    expect($doubleDecker->seats()->count())->toBe(67)
        ->and($doubleDecker->seats()->where('type', SeatType::Sleeper)->count())->toBe(18)
        ->and($doubleDecker->seats()->where('type', SeatType::Accessible)->count())->toBe(1)
        ->and($doubleDecker->seats()->where('deck', 2)->count())->toBe(48);
});

it('regenerates seats when the layout changes and there are no reservations', function (): void {
    $vehicle = Vehicle::factory()->create();
    expect($vehicle->seats()->count())->toBe(12);

    $vehicle->update(['layout' => LayoutPresets::conventional()]);
    app(GenerateSeatsFromLayout::class)->handle($vehicle);

    expect($vehicle->seats()->count())->toBe(45)
        ->and(Seat::query()->where('vehicle_id', $vehicle->id)->where('number', '45')->value('type'))->toBe(SeatType::Accessible);
});
