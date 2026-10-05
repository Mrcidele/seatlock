<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

final class InvalidWebhookSignature extends ApiProblem
{
    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(401, 'Assinatura inválida', $this->getMessage() !== '' ? $this->getMessage() : null, ProblemDetails::typeUri('invalid-webhook-signature'));
    }
}
