<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;

final class LockNotHeld extends ApiProblem
{
    public function __construct()
    {
        parent::__construct('A reserva temporária desses assentos expirou ou pertence a outro carrinho. Selecione os assentos novamente.');
    }

    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(409, 'Reserva temporária expirada', $this->getMessage(), ProblemDetails::typeUri('lock-not-held'));
    }
}
