<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class StoreReserveRequest extends QuoteReserveRequest
{
    public function rules(): array
    {
        $maxInstallments = config('hotel.payments.max_installments', 12);

        return [
            ...parent::rules(),
            'guests' => ['required', 'array', 'min:1', 'max:20'],
            'guests.*.name' => ['required', 'string', 'max:100'],
            'guests.*.last_name' => ['required', 'string', 'max:100'],
            'guests.*.phone' => ['required', 'string', 'regex:/^\+?\d{10,15}$/'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'payments' => ['sometimes', 'array', 'max:10'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.value' => ['required', 'numeric', 'min:0.01'],
            'payments.*.installments' => ['sometimes', 'integer', "between:1,{$maxInstallments}"],
        ];
    }

    public function messages(): array
    {
        return [
            'guests.*.phone.regex' => 'O telefone deve conter de 10 a 15 dígitos (ex.: 5571999999999).',
        ];
    }
}
