<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use App\Http\Problems\ApiProblem;
use App\Http\Problems\ProblemDetails;
use App\Models\Seat;

/**
 * Assento ocupado (vendido no banco ou travado por outro carrinho).
 */
final class SeatsUnavailable extends ApiProblem
{
    /**
     * @param  list<string>  $seatIds
     * @param  list<Seat>  $alternatives
     */
    public function __construct(
        public readonly array $seatIds,
        public readonly array $alternatives = [],
    ) {
        parent::__construct('Um ou mais assentos acabaram de ser ocupados.');
    }

    /**
     * @param  list<Seat>  $alternatives
     */
    public function withAlternatives(array $alternatives): self
    {
        return new self($this->seatIds, $alternatives);
    }

    public function toProblem(): ProblemDetails
    {
        return new ProblemDetails(
            status: 409,
            title: 'Assento indisponível',
            detail: $this->getMessage(),
            type: ProblemDetails::typeUri('seat-unavailable'),
            extensions: [
                'conflicting_seat_ids' => $this->seatIds,
                'alternatives' => array_map(fn (Seat $seat): array => [
                    'id' => $seat->id,
                    'number' => $seat->number,
                    'type' => $seat->type->value,
                    'deck' => $seat->deck,
                ], $this->alternatives),
            ],
        );
    }
}
