<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização por hotel feita via RoomPolicy no controller.
    }

    public function rules(): array
    {
        return [
            // Um PUT completo reenvia o hotel atual: aceito se for o mesmo; outro hotel é transferência (proibida).
            'hotel_id' => ['sometimes', 'required', 'integer:strict', Rule::in([$this->route('room')->hotel_id])],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'capacity' => ['sometimes', 'integer:strict', 'between:1,20'],
            'inventory' => ['sometimes', 'integer:strict', 'between:1,1000'],
            'daily_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        $transfer = 'Não é permitido transferir um quarto para outro hotel.';

        return ['hotel_id.required' => $transfer, 'hotel_id.integer' => $transfer, 'hotel_id.in' => $transfer];
    }
}
