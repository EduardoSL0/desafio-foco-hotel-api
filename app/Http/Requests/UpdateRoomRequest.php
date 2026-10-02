<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização por hotel feita via RoomPolicy no controller.
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'capacity' => ['sometimes', 'integer', 'between:1,20'],
            'inventory' => ['sometimes', 'integer', 'between:1,1000'],
            'daily_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'hotel_id.prohibited' => 'Não é permitido transferir um quarto para outro hotel.',
        ];
    }
}
