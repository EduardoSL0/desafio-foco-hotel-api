<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
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
        ];
    }
}
