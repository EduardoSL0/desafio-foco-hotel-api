<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Recepcionistas não gerenciam usuários (403 antes da validação). As regras finas
     * (perfil e hotel que cada um pode atribuir) ficam na UserPolicy, no controller.
     */
    public function authorize(): bool
    {
        return $this->user()?->role !== UserRole::Receptionist;
    }

    protected function prepareForValidation(): void
    {
        // Gerentes cadastram sempre no próprio hotel; o campo pode ser omitido.
        if (! $this->has('hotel_id')
            && $this->input('role') !== UserRole::Admin->value
            && $this->user()?->role === UserRole::Manager) {
            $this->merge(['hotel_id' => $this->user()->hotel_id]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'hotel_id' => [
                Rule::requiredIf(fn () => $this->input('role') !== UserRole::Admin->value),
                Rule::prohibitedIf(fn () => $this->input('role') === UserRole::Admin->value),
                'nullable', 'integer:strict', 'exists:hotels,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'hotel_id.required' => 'Gerentes e recepcionistas precisam estar vinculados a um hotel.',
            'hotel_id.prohibited' => 'Administradores não são vinculados a um hotel.',
        ];
    }
}
