<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validação de criação (POST) e atualização (PUT/PATCH) de promoções. */
class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização feita via PromotionPolicy no controller.
    }

    public function rules(): array
    {
        /** @var Promotion|null $promotion */
        $promotion = $this->route('promotion');
        $creating = $promotion === null;
        $hotelId = $creating ? $this->input('hotel_id') : $promotion->hotel_id;
        $required = $creating ? 'required' : 'sometimes';

        return [
            'hotel_id' => $creating ? ['required', 'integer', 'exists:hotels,id'] : ['prohibited'],
            // O quarto (opcional) precisa pertencer ao hotel da promoção.
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('hotel_id', $hotelId)->whereNull('deleted_at')],
            'name' => [$required, 'string', 'max:120'],
            'discount_percent' => [$required, 'numeric', 'min:0.01', 'max:100'],
            'starts_at' => [$required, 'date_format:Y-m-d'],
            'ends_at' => [$required, 'date_format:Y-m-d', 'after_or_equal:'.($this->input('starts_at') ?? $promotion?->starts_at?->toDateString() ?? 'starts_at')],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.exists' => 'O quarto informado não pertence ao hotel da promoção.',
            'hotel_id.prohibited' => 'Não é permitido transferir uma promoção para outro hotel.',
        ];
    }
}
