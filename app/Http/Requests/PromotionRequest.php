<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use App\Support\DateInput;
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
            // Na edição o hotel pode ser reenviado (PUT completo), mas não trocado.
            'hotel_id' => $creating
                ? ['required', 'integer:strict', 'exists:hotels,id']
                : ['sometimes', 'required', 'integer:strict', Rule::in([$promotion->hotel_id])],
            // O quarto (opcional) precisa pertencer ao hotel da promoção.
            'room_id' => ['nullable', 'integer:strict', Rule::exists('rooms', 'id')->where('hotel_id', $hotelId)->whereNull('deleted_at')],
            'name' => [$required, 'string', 'max:120'],
            'discount_percent' => [$required, 'numeric', 'min:0.01', 'max:100'],
            'starts_at' => ['bail', $required, 'date_format:Y-m-d'],
            'ends_at' => ['bail', $required, 'date_format:Y-m-d', ...$this->endsAfterStart($promotion)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * O fim não pode ser antes do início (o enviado agora ou o já gravado, na edição).
     *
     * @return list<string>
     */
    private function endsAfterStart(?Promotion $promotion): array
    {
        $start = $this->has('starts_at') ? $this->input('starts_at') : $promotion?->starts_at?->toDateString();

        return DateInput::isDate($start) ? ['after_or_equal:'.$start] : [];
    }

    public function messages(): array
    {
        return [
            'room_id.exists' => 'O quarto informado não pertence ao hotel da promoção.',
            ...($this->route('promotion') === null ? [] : [
                'hotel_id.required' => 'Não é permitido transferir uma promoção para outro hotel.',
                'hotel_id.integer' => 'Não é permitido transferir uma promoção para outro hotel.',
                'hotel_id.in' => 'Não é permitido transferir uma promoção para outro hotel.',
            ]),
        ];
    }
}
