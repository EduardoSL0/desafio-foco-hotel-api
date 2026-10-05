<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;

/** @mixin Payment */
class PaymentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reserve_id' => $this->reserve_id,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'value' => (float) $this->value,
            'installments' => $this->installments,
            'interest' => (float) $this->interest,
            'source' => $this->source,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
        ];
    }
}
