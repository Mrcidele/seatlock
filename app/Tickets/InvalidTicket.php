<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

final class InvalidTicket extends ApiProblem
{
    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(422, 'Bilhete inválido', $this->getMessage(), ProblemDetails::typeUri('invalid-ticket'));
    }
}
