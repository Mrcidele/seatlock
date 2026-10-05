<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ResolvesLeg;
use Illuminate\Foundation\Http\FormRequest;

class SeatMapRequest extends FormRequest
{
    use ResolvesLeg;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->legRules();
    }
}
