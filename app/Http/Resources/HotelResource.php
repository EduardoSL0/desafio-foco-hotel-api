<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Hotel */
class HotelResource extends JsonResource
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
