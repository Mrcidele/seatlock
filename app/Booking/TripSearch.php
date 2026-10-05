<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\SeatType;
use App\Enums\TripStatus;
use App\Models\Stop;
use App\Models\Trip;
use App\ValueObjects\Leg;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class TripSearch
{
    public function __construct(
        private SeatAvailability $availability,
        private FareCalculator $fares,
    ) {}

    /**
     * @return list<TripSearchResult>
     */
    public function search(?string $originCity, ?string $destinationCity, ?CarbonImmutable $date): array
    {
        $query = Trip::query()
            ->with(['route.stops', 'vehicle'])
            ->where('status', TripStatus::Scheduled)
            ->where('departure_at', '>', now())
            ->orderBy('departure_at')
            ->limit(50);

        if ($date !== null) {
            $query->whereBetween('departure_at', [$date->startOfDay(), $date->endOfDay()]);
        }

        foreach (array_filter([$originCity, $destinationCity]) as $city) {
            $query->whereHas('route.stops', fn (Builder $stops): Builder => $stops->whereRaw('lower(city) = ?', [mb_strtolower($city)]));
        }

        $results = [];

        foreach ($query->get() as $trip) {
            $leg = $this->legFor($trip, $originCity, $destinationCity);

            if ($leg === null) {
                continue;
            }

            $results[] = new TripSearchResult(
                trip: $trip,
                leg: $leg,
                availableSeats: $this->availability->availableSeats($trip, $leg)->count(),
                fromPrice: $this->fares->priceFor($trip, $leg, SeatType::Conventional),
            );
        }

        return $results;
    }

    private function legFor(Trip $trip, ?string $originCity, ?string $destinationCity): ?Leg
    {
        $stops = $trip->stops();
        $last = $stops->count() - 1;

        $find = fn (?string $city, int $default): ?int => $city === null || $city === ''
            ? $default
            : $stops->first(fn (Stop $stop): bool => mb_strtolower($stop->city) === mb_strtolower($city))?->sequence;

        $origin = $find($originCity, 0);
        $destination = $find($destinationCity, $last);

        if ($origin === null || $destination === null || $origin >= $destination) {
            return null;
        }

        return new Leg($origin, $destination);
    }
}
