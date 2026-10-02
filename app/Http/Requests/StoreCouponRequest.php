<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
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
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'code' => ['required', 'string', 'alpha_dash', 'max:40', 'unique:coupons,code'],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'numeric', 'min:0.01', ...($this->input('type') === DiscountType::Percent->value ? ['max:100'] : [])],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
