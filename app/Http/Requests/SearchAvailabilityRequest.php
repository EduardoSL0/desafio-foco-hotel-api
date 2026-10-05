<?php

namespace App\Http\Requests;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;

class SearchAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['bail', 'required', 'date_format:Y-m-d', ...DateInput::compareWith('after', 'check_in', $this->input('check_in')), 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'guests' => ['sometimes', 'integer', 'between:1,20'],
            'hotel_id' => ['sometimes', 'integer', 'exists:hotels,id'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'between:1,10000'],
        ];
    }
}
