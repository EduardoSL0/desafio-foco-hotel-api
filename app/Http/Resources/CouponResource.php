<?php

namespace App\Http\Resources;

use App\Models\Coupon;
use Illuminate\Http\Request;

/** @mixin Coupon */
class CouponResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => (float) $this->value,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'active' => $this->active,
        ];
    }
}
