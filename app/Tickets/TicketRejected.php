<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;
use Carbon\CarbonImmutable;

/**
 * Bilhete autêntico, mas que não pode embarcar (já usado, cancelado, outra viagem).
 */
final class TicketRejected extends ApiProblem
{
    public function __construct(string $message, private readonly string $slug, private readonly ?CarbonImmutable $usedAt = null)
    {
        parent::__construct($message);
    }

    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(
            status: 409,
            title: 'Embarque não autorizado',
            detail: $this->getMessage(),
            type: ProblemDetails::typeUri($this->slug),
            extensions: array_filter(['used_at' => $this->usedAt?->toIso8601String()]),
        );
    }
}
