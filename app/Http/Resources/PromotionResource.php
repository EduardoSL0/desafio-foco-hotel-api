<?php

namespace App\Http\Resources;

use App\Models\Promotion;
use Illuminate\Http\Request;

/** @mixin Promotion */
class PromotionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'room_id' => $this->room_id,
            'name' => $this->name,
            'discount_percent' => (float) $this->discount_percent,
            'starts_at' => $this->starts_at->toDateString(),
            'ends_at' => $this->ends_at->toDateString(),
            'active' => $this->active,
        ];
    }
}
