<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

final class MissingCartId extends ApiProblem
{
    public function __construct()
    {
        parent::__construct('Envie o header X-Cart-Id (8 a 64 caracteres alfanuméricos ou hífen) identificando o carrinho.');
    }

    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(400, 'Carrinho não identificado', $this->getMessage(), ProblemDetails::typeUri('missing-cart-id'));
    }
}
