<?php

declare(strict_types=1);

namespace App\Payments\Actions;

use App\Booking\Exceptions\OrderNotModifiable;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Data\ChargeRequest;
use App\Payments\Exceptions\PaymentGatewayError;
use App\Payments\Exceptions\UnsupportedPaymentMethod;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

/**
 * Inicia a cobrança de um pedido pendente.
 *
 * O registro Payment é criado antes de chamar o provedor (e o ID dele vira
 * a chave de idempotência lá), e a chamada HTTP acontece fora de transação.
 */
final readonly class StartPayment
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private ApplyPaymentStatus $applyStatus,
    ) {}

    public function handle(Order $order, PaymentMethod $method, ?string $cardToken = null, ?string $cardBrand = null): Payment
    {
        $gateway = $this->gateways->forMethod($method);

        if (! $gateway->supports($method)) {
            throw new UnsupportedPaymentMethod("O provedor {$gateway->name()} não aceita {$method->value}.");
        }

        $payment = DB::transaction(function () use ($order, $method, $gateway): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Pending) {
                throw OrderNotModifiable::notPending();
            }

            if ($order->isExpiredAt(now())) {
                throw OrderNotModifiable::expired();
            }

            // Pix pendente ainda válido: devolve o mesmo QR Code.
            $pending = $order->payments()
                ->where('status', PaymentStatus::Pending)
                ->where('method', $method)
                ->whereNotNull('external_id')
                ->latest()
                ->first();

            if ($pending !== null && $method === PaymentMethod::Pix) {
                return $pending;
            }

            return Payment::query()->create([
                'order_id' => $order->id,
                'gateway' => $gateway->name(),
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'amount_cents' => $order->total_cents,
                'currency' => $order->currency,
            ]);
        });

        if ($payment->external_id !== null) {
            return $payment;
        }

        $order->loadMissing('user');

        try {
            $result = $gateway->charge(new ChargeRequest(
                paymentId: $payment->id,
                orderId: $order->id,
                amount: $payment->amount,
                method: $method,
                payerEmail: $order->user->email,
                description: "Passagem - pedido {$order->id}",
                expiresAt: $order->expires_at,
                cardToken: $cardToken,
                cardBrand: $cardBrand,
            ));
        } catch (PaymentGatewayError $e) {
            $payment->update(['status' => PaymentStatus::Declined, 'failure_reason' => 'gateway_error']);

            throw $e;
        }

        $payment->update([
            'external_id' => $result->externalId,
            'pix_copy_paste' => $result->pixCopyPaste,
            'pix_expires_at' => $result->pixExpiresAt,
            'card_brand' => $result->cardBrand,
            'card_last_four' => $result->cardLastFour,
            'failure_reason' => $result->failureReason,
        ]);

        if ($result->status !== PaymentStatus::Pending) {
            $this->applyStatus->handle($payment, $result->status);
        }

        return $payment->refresh();
    }
}
