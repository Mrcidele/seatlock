<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

class RebookOrderRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'trip_id' => ['required', 'string', 'ulid', 'exists:trips,id'],
            'seats' => ['required', 'array', 'min:1'],
            'seats.*.reservation_id' => ['required', 'string', 'ulid', 'distinct'],
            'seats.*.seat_id' => ['required', 'string', 'ulid', 'distinct'],
        ];
    }

    public function trip(): Trip
    {
        return Trip::query()->with('route.stops')->findOrFail($this->string('trip_id')->toString());
    }

    /**
     * @return array<string, string>
     */
    public function seatByReservation(): array
    {
        $map = [];
        $seats = $this->validated('seats');

        foreach (is_array($seats) ? $seats : [] as $row) {
            if (is_array($row) && is_string($row['reservation_id'] ?? null) && is_string($row['seat_id'] ?? null)) {
                $map[$row['reservation_id']] = $row['seat_id'];
            }
        }

        return $map;
    }
}
