<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização feita via UserPolicy no controller.
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $role = $this->input('role', $target->role->value);
        $ownAccount = $this->user()?->id === $target->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['sometimes', 'required', 'string', Password::min(8)->letters()->numbers()],
            // Trocar a própria senha exige a atual: um token roubado não basta para tomar a conta.
            'current_password' => [Rule::requiredIf(fn () => $ownAccount && $this->has('password')), 'nullable', 'string', 'current_password:sanctum'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'hotel_id' => [
                // Gerente/recepção sempre têm hotel: obrigatório se enviado (null não vale) ou se o usuário ainda não tem.
                Rule::requiredIf(fn () => $role !== UserRole::Admin->value && ($this->has('hotel_id') || $target->hotel_id === null)),
                Rule::prohibitedIf(fn () => $role === UserRole::Admin->value && $this->filled('hotel_id')),
                'nullable', 'integer:strict', 'exists:hotels,id',
            ],
        ];
    }
}
