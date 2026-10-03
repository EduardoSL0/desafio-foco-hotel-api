<?php

namespace App\Http\Requests;

use App\Support\DateInput;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A ocupação é calculada noite a noite: períodos sem limite (ex.: até 9999) travariam a requisição.
        $checkIn = $this->input('check_in');
        $maxCheckOut = DateInput::isDate($checkIn) ? ['before_or_equal:'.CarbonImmutable::parse($checkIn)->addYear()->toDateString()] : [];

        return [
            'check_in' => ['bail', 'required', 'date_format:Y-m-d'],
            'check_out' => ['bail', 'required', 'date_format:Y-m-d', ...DateInput::compareWith('after', 'check_in', $checkIn), ...$maxCheckOut],
        ];
    }

    public function messages(): array
    {
        return ['check_out.before_or_equal' => 'O período máximo da consulta é de 1 ano.'];
    }
}
