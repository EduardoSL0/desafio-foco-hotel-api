<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuoteReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Opcional, como o hotelCode do XML: quando enviado, o quarto precisa ser desse hotel.
            'hotel_id' => ['sometimes', 'integer:strict', 'exists:hotels,id'],
            'room_id' => ['required', 'integer:strict', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'check_in' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['bail', 'required', 'date_format:Y-m-d', ...DateInput::compareWith('after', 'check_in', $this->input('check_in')), 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('hotel_id') || $validator->errors()->hasAny(['hotel_id', 'room_id'])) {
                return;
            }

            $roomHotel = Room::query()->whereKey($this->input('room_id'))->value('hotel_id');

            if ((int) $roomHotel !== (int) $this->input('hotel_id')) {
                $validator->errors()->add('room_id', 'O quarto informado não pertence ao hotel informado.');
            }
        });
    }
}
