<?php

namespace App\Http\Requests;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in' => ['bail', 'required', 'date_format:Y-m-d'],
            'check_out' => ['bail', 'required', 'date_format:Y-m-d', ...DateInput::compareWith('after', 'check_in', $this->input('check_in'))],
        ];
    }
}
