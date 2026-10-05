<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

final class PaymentGatewayError extends ApiProblem
{
    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(502, 'Falha no provedor de pagamento', 'Não foi possível falar com o provedor de pagamento. Tente novamente.', ProblemDetails::typeUri('payment-gateway-error'));
    }
}
