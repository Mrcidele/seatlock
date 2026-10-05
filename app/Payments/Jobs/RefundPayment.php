<?php

declare(strict_types=1);

namespace App\Payments\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentGatewayManager;
use App\ValueObjects\Money;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Estorna um pagamento no provedor. Roda na fila com retentativas: o
 * dinheiro do cliente precisa voltar mesmo se o provedor oscilar.
 */
final class RefundPayment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 300, 900, 1800, 3600];

    public function __construct(
        public readonly string $paymentId,
        /** Null = valor total. */
        public readonly ?int $amountCents = null,
        public readonly string $reason = 'refund',
    ) {
        $this->onQueue('payments');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->paymentId.':'.($this->amountCents ?? 'full');
    }

    public function handle(PaymentGatewayManager $gateways): void
    {
        $payment = Payment::query()->findOrFail($this->paymentId);

        if ($payment->status !== PaymentStatus::Approved) {
            Log::info('payment.refund_skipped', ['payment_id' => $payment->id, 'status' => $payment->status->value]);

            return;
        }

        $amount = Money::of($this->amountCents ?? $payment->amount_cents, $payment->currency);
        $result = $gateways->gateway($payment->gateway)->refund($payment, $amount);

        DB::transaction(function () use ($payment, $result): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status->canTransitionTo(PaymentStatus::Refunded)) {
                $locked->status = PaymentStatus::Refunded;
                $locked->refunded_cents = $result->refundedCents;
                $locked->refunded_at = now();
                $locked->save();
            }
        });

        Log::info('payment.refunded', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount_cents' => $result->refundedCents,
            'reason' => $this->reason,
        ]);
    }
}
