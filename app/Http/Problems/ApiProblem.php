<?php

declare(strict_types=1);

namespace App\Http\Problems;

use RuntimeException;

/**
 * Exceção de domínio que sabe se representar como Problem Details.
 */
abstract class ApiProblem extends RuntimeException
{
    abstract public function toProblem(): ProblemDetails;
}
