<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Room */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'hotel' => new HotelResource($this->whenLoaded('hotel')),
            'external_code' => $this->external_code,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'inventory' => $this->inventory,
            'daily_price' => $this->daily_price === null ? null : (float) $this->daily_price,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
