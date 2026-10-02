<?php

namespace App\Http\Resources;

use App\Models\Daily;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Reserve */
class ReserveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_code' => $this->external_code,
            'status' => $this->status->value,
            'source' => $this->source,
            'hotel' => new HotelResource($this->whenLoaded('hotel')),
            'room' => new RoomResource($this->whenLoaded('room')),
            'coupon_code' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->nights(),
            'amounts' => [
                'subtotal' => (float) $this->subtotal,
                'discount' => (float) $this->discount,
                'fees' => (float) $this->fees,
                'total' => (float) $this->total,
                'paid' => $this->paidAmount(),
                'balance' => $this->balance(),
            ],
            'guests' => GuestResource::collection($this->whenLoaded('guests')),
            'dailies' => $this->whenLoaded('dailies', fn () => $this->dailies
                ->sortBy(fn (Daily $d) => $d->date->toDateString())
                ->values()
                ->map(fn (Daily $d) => [
                    'date' => $d->date->toDateString(),
                    'value' => (float) $d->value,
                    'discount' => (float) $d->discount,
                ])),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
