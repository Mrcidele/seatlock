<?php

declare(strict_types=1);

namespace App\Booking\Actions;

use App\Booking\Exceptions\OrderNotModifiable;
use App\Booking\RefundPolicy;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Payments\Jobs\RefundPayment;

/**
 * Cancelamento após a compra: libera o assento e estorna conforme a
 * antecedência em relação ao embarque.
 */
final readonly class CancelPaidOrder
{
    public function __construct(
        private RefundPolicy $policy,
        private VoidSale $voidSale,
    ) {}

    public function handle(Order $order): Order
    {
        $order->loadMissing('trip.route.stops');
        $departure = $order->trip->departureAtStop($order->origin_index);
        $refund = $this->policy->refundFor($order->total, $departure);

        if ($refund === null) {
            throw new OrderNotModifiable('O prazo para cancelamento desta passagem já terminou.', 'cancellation-window-closed');
        }

        $payment = $order->payments()->where('status', PaymentStatus::Approved)->latest('approved_at')->first();

        $voided = $this->voidSale->handle(
            $order,
            $refund->isZero() ? OrderStatus::Cancelled : OrderStatus::Refunded,
            'customer_cancellation',
            $refund->cents,
        );

        if ($payment !== null && ! $refund->isZero()) {
            RefundPayment::dispatch($payment->id, $refund->cents, 'customer_cancellation');
        }

        return $voided;
    }
}
