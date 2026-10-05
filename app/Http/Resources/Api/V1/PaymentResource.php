<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'gateway' => $this->gateway,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'refunded' => $this->refunded,
            'pix' => $this->pix_copy_paste === null ? null : [
                'copy_paste' => $this->pix_copy_paste,
                'expires_at' => $this->pix_expires_at?->toIso8601String(),
            ],
            'card' => $this->card_last_four === null ? null : [
                'brand' => $this->card_brand,
                'last_four' => $this->card_last_four,
            ],
            'failure_reason' => $this->failure_reason,
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
