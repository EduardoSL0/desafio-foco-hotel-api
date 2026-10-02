<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização por hotel feita via RoomPolicy no controller.
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['sometimes', 'integer', 'between:1,20'],
            'inventory' => ['sometimes', 'integer', 'between:1,1000'],
            'daily_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
