<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Dados de cartão nunca devem chegar aqui: o front tokeniza no SDK do
     * provedor e envia apenas card_token.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'card_token' => ['required_if:method,card', 'nullable', 'string', 'max:255'],
            'card_brand' => ['nullable', 'string', 'max:30'],
            'card_number' => ['prohibited'],
            'number' => ['prohibited'],
            'cvv' => ['prohibited'],
            'cvc' => ['prohibited'],
            'security_code' => ['prohibited'],
            'expiry' => ['prohibited'],
            'expiration_date' => ['prohibited'],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->string('method')->toString());
    }

    public function optional(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
