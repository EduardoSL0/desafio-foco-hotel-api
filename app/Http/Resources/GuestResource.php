<?php

namespace App\Http\Resources;

use App\Models\Guest;
use Illuminate\Http\Request;

/** @mixin Guest */
class GuestResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
