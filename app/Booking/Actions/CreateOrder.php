<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Booking\AlternativeSeats;
use App\Booking\BookingSettings;
use App\Booking\Data\PassengerData;
use App\Booking\Exceptions\LockNotHeld;
use App\Booking\Exceptions\OrderNotModifiable;
use App\Booking\Exceptions\SeatsUnavailable;
use App\Booking\Exceptions\TripNotBookable;
use App\Booking\FareCalculator;
use App\Booking\Jobs\ExpireOrderJob;
use App\Booking\SeatAvailability;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Passenger;
use App\Models\Reservation;
use App\Models\Seat;
use App\Models\Trip;
use App\Models\User;
use App\SeatLock\SeatLockService;
use App\ValueObjects\Leg;
use App\ValueObjects\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria o pedido Pending para assentos que o carrinho já travou.
 * O prazo do pedido é o prazo do lock.
 */
final readonly class CreateOrder
{
    public function __construct(
        private SeatLockService $locks,
        private SeatAvailability $availability,
        private AlternativeSeats $alternatives,
        private FareCalculator $fares,
    ) {}

    /**
     * @param  list<PassengerData>  $passengers
     */
    public function handle(User $user, Trip $trip, Leg $leg, string $owner, array $passengers): Order
    {
        if (! $trip->isBookable()) {
            throw new TripNotBookable;
        }

        $seatIds = array_map(fn (PassengerData $p): string => $p->seatId, $passengers);
        $seats = Seat::query()->where('vehicle_id', $trip->vehicle_id)->whereIn('id', $seatIds)->get()->keyBy('id');

        if ($seats->count() !== count($seatIds)) {
            throw ValidationException::withMessages(['passengers' => 'Um ou mais assentos não pertencem ao veículo desta viagem.']);
        }

        $sold = $this->availability->unavailableAmong($trip, $leg, $seatIds);

        if ($sold !== []) {
            throw new SeatsUnavailable($sold, $this->alternatives->suggest($trip, $leg, $sold, $seatIds));
        }

        $heldUntil = $this->locks->heldUntil($trip->id, $seatIds, $leg, $owner);

        if ($heldUntil === null) {
            throw new LockNotHeld;
        }

        try {
            return DB::transaction(function () use ($user, $trip, $leg, $owner, $passengers, $seats, $heldUntil): Order {
                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'trip_id' => $trip->id,
                    'origin_index' => $leg->origin,
                    'destination_index' => $leg->destination,
                    'status' => OrderStatus::Pending,
                    'total_cents' => 0,
                    'currency' => BookingSettings::currency(),
                    'lock_owner' => $owner,
                    'expires_at' => $heldUntil,
                ]);

                $total = Money::zero(BookingSettings::currency());

                foreach ($passengers as $data) {
                    $seat = $seats->get($data->seatId);
                    assert($seat instanceof Seat);
                    $price = $this->fares->priceFor($trip, $leg, $seat->type);

                    $passenger = Passenger::query()->create([
                        'name' => $data->name,
                        'document' => $data->document,
                        'email' => $data->email,
                        'phone' => $data->phone,
                    ]);

                    Reservation::query()->create([
                        'order_id' => $order->id,
                        'trip_id' => $trip->id,
                        'seat_id' => $seat->id,
                        'passenger_id' => $passenger->id,
                        'origin_index' => $leg->origin,
                        'destination_index' => $leg->destination,
                        'price_cents' => $price->cents,
                        'currency' => $price->currency,
                        'status' => ReservationStatus::Pending,
                    ]);

                    $total = $total->add($price);
                }

                $order->total = $total;
                $order->save();

                // Expira o pedido (e libera os assentos) quando o prazo vencer.
                ExpireOrderJob::scheduleFor($order);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            $existing = Order::query()
                ->where('lock_owner', $owner)
                ->where('trip_id', $trip->id)
                ->where('status', OrderStatus::Pending)
                ->value('id');

            throw is_string($existing) ? OrderNotModifiable::alreadyPending($existing) : $e;
        }
    }
}
