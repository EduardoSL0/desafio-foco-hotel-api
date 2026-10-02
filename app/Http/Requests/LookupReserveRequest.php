<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:8', 'alpha_num'],
            'last_name' => ['required', 'string', 'max:100'],
        ];
    }
}
