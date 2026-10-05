<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

/**
 * Operação não permitida no estado atual do pedido (pagar pedido expirado,
 * renovar além do limite, cancelar fora do prazo...).
 */
final class OrderNotModifiable extends ApiProblem
{
    public function __construct(string $message, private readonly string $slug = 'order-not-modifiable')
    {
        parent::__construct($message);
    }

    public static function expired(): self
    {
        return new self('O prazo para pagamento deste pedido expirou.', 'order-expired');
    }

    public static function renewalLimitReached(): self
    {
        return new self('O prazo deste pedido já foi renovado o número máximo de vezes.', 'renewal-limit-reached');
    }

    public static function notPending(): self
    {
        return new self('Este pedido não está mais aguardando pagamento.', 'order-not-pending');
    }

    public static function alreadyPending(string $orderId): self
    {
        return new self("Este carrinho já tem o pedido {$orderId} aguardando pagamento nesta viagem.", 'order-already-pending');
    }

    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(409, 'Operação não permitida para o pedido', $this->getMessage(), ProblemDetails::typeUri($this->slug));
    }
}
