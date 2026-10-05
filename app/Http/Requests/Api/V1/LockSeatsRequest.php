<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Booking\BookingSettings;
use App\Http\Requests\Api\V1\Concerns\ResolvesLeg;
use Illuminate\Foundation\Http\FormRequest;

class LockSeatsRequest extends FormRequest
{
    use ResolvesLeg;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...$this->legRules(),
            'seat_ids' => ['required', 'array', 'min:1', 'max:'.BookingSettings::maxSeatsPerOrder()],
            'seat_ids.*' => ['required', 'string', 'ulid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function seatIds(): array
    {
        $ids = $this->validated('seat_ids');

        return is_array($ids) ? array_values(array_map(fn (mixed $id): string => is_string($id) ? $id : '', $ids)) : [];
    }
}
