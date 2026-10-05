<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\User;
use App\Payments\Actions\StartPayment;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    /**
     * Paga um pedido pendente com Pix (devolve o copia e cola) ou cartão
     * tokenizado no cliente. Exige Idempotency-Key.
     */
    public function store(StorePaymentRequest $request, string $order, StartPayment $startPayment): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $order = $user->orders()->findOrFail($order);

        $payment = $startPayment->handle(
            $order,
            $request->paymentMethod(),
            $request->optional('card_token'),
            $request->optional('card_brand'),
        );

        return (new OrderResource($order->refresh()))
            ->additional(['payment_id' => $payment->id])
            ->response()
            ->setStatusCode(201);
    }
}
