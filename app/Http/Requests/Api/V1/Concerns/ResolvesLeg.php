<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\Trip;
use App\ValueObjects\Leg;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

trait ResolvesLeg
{
    public function legFor(Trip $trip): Leg
    {
        try {
            return $trip->leg($this->integer('origin'), $this->integer('destination'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['destination' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function legRules(): array
    {
        return [
            'origin' => ['required', 'integer', 'min:0'],
            'destination' => ['required', 'integer', 'gt:origin'],
        ];
    }
}
