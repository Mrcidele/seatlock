<?php

declare(strict_types=1);

namespace App\Payments\Actions;

use App\Booking\Actions\ConfirmOrder;
use App\Booking\Actions\VoidSale;
use App\Booking\ConfirmationOutcome;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Jobs\RefundPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aplica um novo status de pagamento (vindo da resposta síncrona ou de um
 * webhook) e dispara as consequências no pedido.
 *
 * As transições são monotônicas: eventos duplicados ou atrasados (ex.: um
 * "pending" que chega depois do "approved") são ignorados.
 */
final readonly class ApplyPaymentStatus
{
    public function __construct(
        private ConfirmOrder $confirmOrder,
        private VoidSale $voidSale,
    ) {}

    /**
     * @return bool se o status foi aplicado (false = evento ignorado)
     */
    public function handle(Payment $payment, PaymentStatus $status, ?int $refundedCents = null): bool
    {
        $applied = DB::transaction(function () use ($payment, $status, $refundedCents): bool {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $locked->status->canTransitionTo($status)) {
                Log::info('payment.status_ignored', ['payment_id' => $locked->id, 'from' => $locked->status->value, 'to' => $status->value]);

                return false;
            }

            $locked->status = $status;

            if ($status === PaymentStatus::Approved) {
                $locked->approved_at = now();
            }

            if ($status === PaymentStatus::Refunded) {
                $locked->refunded_at = now();
                $locked->refunded_cents = $refundedCents ?? $locked->amount_cents;
            }

            $locked->save();

            return true;
        });

        if (! $applied) {
            return false;
        }

        match ($status) {
            PaymentStatus::Approved => $this->onApproved($payment),
            PaymentStatus::Refunded => $this->onRefundedByProvider($payment),
            default => null,
        };

        return true;
    }

    private function onApproved(Payment $payment): void
    {
        $outcome = $this->confirmOrder->handle($payment->order_id);

        Log::info('payment.approved', ['payment_id' => $payment->id, 'order_id' => $payment->order_id, 'outcome' => $outcome->value]);

        // Dinheiro recebido sem assento para entregar (assento vendido a outro,
        // pedido cancelado ou já pago por outra cobrança): estorno automático.
        // Duplicatas do mesmo pagamento nem chegam aqui (transição ignorada).
        if ($outcome->requiresRefund() || $outcome === ConfirmationOutcome::AlreadyPaid) {
            RefundPayment::dispatch($payment->id, null, "order_{$outcome->value}");
        }
    }

    /**
     * Estorno/chargeback iniciado fora do sistema (painel do provedor):
     * a venda é desfeita e o assento volta a ficar disponível.
     */
    private function onRefundedByProvider(Payment $payment): void
    {
        $order = Order::query()->findOrFail($payment->order_id);

        if ($order->status === OrderStatus::Paid) {
            $this->voidSale->handle($order, OrderStatus::Refunded, 'refunded_by_provider', $payment->refunded_cents);
        }
    }
}
