<?php

namespace App\Http\Requests;

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
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}
