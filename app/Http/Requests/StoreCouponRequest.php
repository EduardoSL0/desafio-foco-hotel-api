<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização feita via CouponPolicy no controller.
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
            'hotel_id' => ['nullable', 'integer:strict', 'exists:hotels,id'],
            'code' => ['required', 'string', 'alpha_dash', 'max:40', 'unique:coupons,code'],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'numeric', 'min:0.01', ...($this->input('type') === DiscountType::Percent->value ? ['max:100'] : ['max:99999999.99'])],
            'valid_from' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'valid_until' => ['bail', 'nullable', 'date_format:Y-m-d', ...DateInput::compareWith('after_or_equal', 'valid_from', $this->input('valid_from'))],
            'max_uses' => ['nullable', 'integer:strict', 'min:1', 'max:1000000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
