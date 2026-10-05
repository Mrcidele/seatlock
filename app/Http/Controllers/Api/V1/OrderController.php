<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Booking\Actions\CancelPendingOrder;
use App\Booking\Actions\CreateOrder;
use App\Booking\Actions\RenewOrder;
use App\Booking\LockOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Pedidos do usuário autenticado.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $this->user($request)->orders()->latest()->paginate(20),
        );
    }

    /**
     * Cria um pedido Pending para os assentos travados pelo carrinho.
     *
     * Exige o header Idempotency-Key: repetir a mesma requisição devolve o
     * mesmo pedido em vez de criar outro.
     */
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $trip = $request->trip();

        $order = $createOrder->handle(
            $this->user($request),
            $trip,
            $request->legFor($trip),
            LockOwner::fromRequest($request),
            $request->passengers(),
        );

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $order): OrderResource
    {
        return new OrderResource($this->findOwned($request, $order));
    }

    /**
     * Desiste de um pedido ainda não pago e libera os assentos.
     */
    public function cancel(Request $request, string $order, CancelPendingOrder $cancel): OrderResource
    {
        return new OrderResource($cancel->handle($this->findOwned($request, $order)));
    }

    /**
     * Renova o prazo de pagamento (e o lock) uma única vez.
     */
    public function renew(Request $request, string $order, RenewOrder $renew): OrderResource
    {
        return new OrderResource($renew->handle($this->findOwned($request, $order)));
    }

    private function findOwned(Request $request, string $id): Order
    {
        return $this->user($request)->orders()->findOrFail($id);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
