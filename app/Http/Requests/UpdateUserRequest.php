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

    public function rules(): array
    {
        $target = $this->route('user');
        $role = $this->input('role', $target->role->value);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['sometimes', 'required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'hotel_id' => [
                Rule::requiredIf(fn () => $role !== UserRole::Admin->value && $target->hotel_id === null && ! $this->has('hotel_id')),
                Rule::prohibitedIf(fn () => $role === UserRole::Admin->value && $this->filled('hotel_id')),
                'nullable', 'integer', 'exists:hotels,id',
            ],
        ];
    }
}
