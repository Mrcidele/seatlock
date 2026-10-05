<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

final class UnsupportedPaymentMethod extends ApiProblem
{
    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(422, 'Forma de pagamento indisponível', $this->getMessage(), ProblemDetails::typeUri('unsupported-payment-method'));
    }
}
