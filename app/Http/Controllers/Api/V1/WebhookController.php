<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Payments\Actions\IngestWebhook;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Recebe notificações dos provedores de pagamento.
     *
     * Responde 202 após gravar o evento; o processamento roda na fila.
     * Eventos repetidos respondem 200 sem reprocessar.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, string $provider, PaymentGatewayManager $gateways, IngestWebhook $ingest): JsonResponse
    {
        abort_unless($gateways->has($provider), 404);

        $accepted = $ingest->handle($gateways->gateway($provider), $request);

        return new JsonResponse(['status' => $accepted ? 'accepted' : 'duplicate'], $accepted ? 202 : 200);
    }
}
