<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;

/** @mixin Room */
class RoomResource extends ApiResource
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
