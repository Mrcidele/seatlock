<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Booking\BookingSettings;
use App\Booking\Data\PassengerData;
use App\Http\Requests\Api\V1\Concerns\ResolvesLeg;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    use ResolvesLeg;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'trip_id' => ['required', 'string', 'ulid', 'exists:trips,id'],
            ...$this->legRules(),
            'passengers' => ['required', 'array', 'min:1', 'max:'.BookingSettings::maxSeatsPerOrder()],
            'passengers.*.seat_id' => ['required', 'string', 'ulid', 'distinct'],
            'passengers.*.name' => ['required', 'string', 'min:3', 'max:120'],
            'passengers.*.document' => ['required', 'string', 'regex:/^[0-9A-Za-z.\-\/]{5,20}$/'],
            'passengers.*.email' => ['nullable', 'email', 'max:255'],
            'passengers.*.phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function trip(): Trip
    {
        return Trip::query()->with('route.stops')->findOrFail($this->string('trip_id')->toString());
    }

    /**
     * @return list<PassengerData>
     */
    public function passengers(): array
    {
        $passengers = $this->validated('passengers');

        if (! is_array($passengers)) {
            return [];
        }

        $result = [];

        foreach ($passengers as $passenger) {
            if (! is_array($passenger)) {
                continue;
            }

            $result[] = new PassengerData(
                seatId: $this->field($passenger, 'seat_id') ?? '',
                name: $this->field($passenger, 'name') ?? '',
                document: preg_replace('/\D+/', '', $this->field($passenger, 'document') ?? '') ?: ($this->field($passenger, 'document') ?? ''),
                email: $this->field($passenger, 'email'),
                phone: $this->field($passenger, 'phone'),
            );
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function field(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
