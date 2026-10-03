<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização feita via ReservePolicy::pay no controller.
    }

    public function rules(): array
    {
        $maxInstallments = config('hotel.payments.max_installments', 12);

        return [
            'method' => ['bail', 'required', 'integer:strict', Rule::enum(PaymentMethod::class)],
            'value' => ['required', 'numeric', 'min:0.01'],
            'installments' => ['sometimes', 'integer:strict', "between:1,{$maxInstallments}"],
        ];
    }
}
