<?php

declare(strict_types=1);

namespace App\Payments\Jobs;

use App\Enums\WebhookEventStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Payments\Actions\ApplyPaymentStatus;
use App\Payments\PaymentGatewayManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    /** Tentativas para o caso "webhook chegou antes de gravarmos o pagamento". */
    public const int MAX_ATTEMPTS = 6;

    public int $tries = self::MAX_ATTEMPTS;

    public function __construct(public readonly string $webhookEventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(PaymentGatewayManager $gateways, ApplyPaymentStatus $applyStatus): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->status === WebhookEventStatus::Processed || $event->status === WebhookEventStatus::Ignored) {
            return;
        }

        $event->increment('attempts');

        $payment = $event->external_payment_id === null ? null : Payment::query()
            ->where('gateway', $event->provider)
            ->where('external_id', $event->external_payment_id)
            ->first();

        if ($payment === null) {
            // Fora de ordem: o provedor notificou antes de terminarmos de gravar
            // a resposta da cobrança. Tenta de novo daqui a pouco.
            if ($this->attempts() < self::MAX_ATTEMPTS) {
                $this->release(min(60, 5 * $this->attempts()));

                return;
            }

            $this->finish($event, WebhookEventStatus::Ignored, 'Pagamento não encontrado.');

            return;
        }

        try {
            $status = $event->reported_status;
            $refunded = null;

            if ($status === null) {
                $current = $gateways->gateway($event->provider)->fetch($payment->external_id ?? '');
                $status = $current->status;
                $refunded = $current->refundedCents > 0 ? $current->refundedCents : null;
            }

            $applied = $applyStatus->handle($payment, $status, $refunded);
        } catch (Throwable $e) {
            $event->update(['status' => WebhookEventStatus::Failed, 'error' => $e->getMessage()]);

            throw $e;
        }

        $this->finish($event, $applied ? WebhookEventStatus::Processed : WebhookEventStatus::Ignored);
    }

    private function finish(WebhookEvent $event, WebhookEventStatus $status, ?string $error = null): void
    {
        $event->update(['status' => $status, 'processed_at' => now(), 'error' => $error]);

        Log::info('webhook.processed', ['event_id' => $event->event_id, 'provider' => $event->provider, 'status' => $status->value]);
    }
}
