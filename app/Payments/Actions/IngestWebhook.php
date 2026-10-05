<?php

declare(strict_types=1);

namespace App\Payments\Actions;

use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use App\Payments\Jobs\ProcessWebhookEvent;
use App\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Recebe o webhook: valida a assinatura, grava o evento (UNIQUE por
 * provedor + ID do evento) e joga o processamento para a fila. Responde
 * rápido para o provedor não considerar timeout e reenviar.
 */
final readonly class IngestWebhook
{
    /**
     * @return bool false quando o evento já tinha sido recebido (duplicado)
     */
    public function handle(PaymentGateway $gateway, Request $request): bool
    {
        $notification = $gateway->parseWebhook($request);
        $id = (string) Str::ulid();

        $inserted = WebhookEvent::query()->insertOrIgnore([
            'id' => $id,
            'provider' => $gateway->name(),
            'event_id' => $notification->eventId,
            'type' => $notification->type,
            'external_payment_id' => $notification->externalPaymentId,
            'reported_status' => $notification->status?->value,
            'payload' => json_encode($notification->payload),
            'status' => WebhookEventStatus::Received->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return false;
        }

        ProcessWebhookEvent::dispatch($id);

        return true;
    }
}
