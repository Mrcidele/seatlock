<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Booking\BookingSettings;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['reservations.seat', 'reservations.passenger', 'reservations.ticket', 'payments']);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'trip_id' => $this->trip_id,
            'leg' => ['origin' => $this->origin_index, 'destination' => $this->destination_index],
            'total' => $this->total,
            'refunded' => $this->refunded,
            'expires_at' => $this->expires_at->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'renewals_left' => $this->status === OrderStatus::Pending
                ? max(0, BookingSettings::maxRenewals() - $this->lock_renewals)
                : 0,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'reservations' => $this->reservations->map(fn (Reservation $r): array => [
                'id' => $r->id,
                'status' => $r->status->value,
                'seat' => ['id' => $r->seat->id, 'number' => $r->seat->number, 'type' => $r->seat->type->value, 'deck' => $r->seat->deck],
                'passenger' => [
                    'name' => $r->passenger->name,
                    'document' => self::maskDocument($r->passenger->document),
                ],
                'price' => $r->price,
                'ticket_id' => $r->ticket?->id,
            ])->values()->all(),
            'payments' => $this->payments->map(fn (Payment $p): array => (new PaymentResource($p))->toArray($request))->values()->all(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private static function maskDocument(string $document): string
    {
        $visible = 3;

        return strlen($document) <= $visible
            ? $document
            : str_repeat('*', strlen($document) - $visible).substr($document, -$visible);
    }
}
