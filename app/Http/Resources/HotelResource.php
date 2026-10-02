<?php

namespace App\Http\Resources;

use App\Models\Hotel;
use Illuminate\Http\Request;

/** @mixin Hotel */
class HotelResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_code' => $this->external_code,
            'name' => $this->name,
            'service_fee_percent' => (float) $this->service_fee_percent,
            'rooms_count' => $this->whenCounted('rooms'),
        ];
    }
}
