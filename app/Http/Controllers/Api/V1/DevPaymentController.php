<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Actions\IngestWebhook;
use App\Payments\Gateways\FakeGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Só existe em ambiente local/testes: simula o provedor fake enviando um
 * webhook assinado (ex.: "o cliente pagou o Pix").
 */
class DevPaymentController extends Controller
{
    public function __invoke(Request $request, string $payment, PaymentGatewayManager $gateways, IngestWebhook $ingest): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(PaymentStatus::class)]]);
        $user = $request->user();
        assert($user instanceof User);

        $payment = Payment::query()
            ->whereIn('order_id', $user->orders()->select('id'))
            ->where('gateway', 'fake')
            ->findOrFail($payment);

        $gateway = $gateways->gateway('fake');
        assert($gateway instanceof FakeGateway);

        $webhook = $gateway->signedWebhook((string) $payment->external_id, PaymentStatus::from((string) $data['status']));
        $simulated = Request::create('/api/v1/webhooks/fake', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => $webhook['headers'][FakeGateway::SIGNATURE_HEADER],
        ], content: $webhook['body']);

        $ingest->handle($gateway, $simulated);

        return new JsonResponse(['status' => 'sent'], 202);
    }
}
