<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

final class TripNotBookable extends ApiProblem
{
    public function __construct()
    {
        parent::__construct('Esta viagem não está mais aberta para venda.');
    }

    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(409, 'Viagem indisponível', $this->getMessage(), ProblemDetails::typeUri('trip-not-bookable'));
    }
}
