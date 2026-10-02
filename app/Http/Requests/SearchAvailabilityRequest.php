<?php

namespace App\Http\Requests;

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
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'guests' => ['sometimes', 'integer', 'between:1,20'],
            'hotel_id' => ['sometimes', 'integer', 'exists:hotels,id'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}
