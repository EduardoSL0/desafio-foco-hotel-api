<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Room;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'payments.*.method' => ['bail', 'required', 'integer:strict', Rule::enum(PaymentMethod::class)],
            'payments.*.value' => ['required', 'numeric', 'min:0.01'],
            'payments.*.installments' => ['sometimes', 'integer:strict', "between:1,{$maxInstallments}"],
        ];
    }

    /**
     * A rota de reserva é pública (motor de reservas), mas registrar pagamento
     * junto com a reserva é exclusivo da equipe do hotel (ex.: balcão). Sem isso,
     * qualquer cliente poderia criar uma reserva já "paga".
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            if (empty($this->input('payments')) || $validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var User|null $user */
            $user = $this->user('sanctum');
            $hotelId = Room::query()->whereKey($this->input('room_id'))->value('hotel_id');

            if (! $user || $hotelId === null || ! $user->worksAt((int) $hotelId)) {
                $validator->errors()->add('payments', 'Somente a equipe autenticada do hotel pode registrar pagamentos junto com a reserva.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'guests.*.phone.regex' => 'O telefone deve conter de 10 a 15 dígitos (ex.: 5571999999999).',
        ];
    }
}
