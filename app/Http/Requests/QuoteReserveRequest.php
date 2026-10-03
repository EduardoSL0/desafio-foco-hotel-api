<?php

namespace App\Http\Requests;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer:strict', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'check_in' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['bail', 'required', 'date_format:Y-m-d', ...DateInput::compareWith('after', 'check_in', $this->input('check_in')), 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}
