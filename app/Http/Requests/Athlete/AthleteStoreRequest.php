<?php

namespace App\Http\Requests\Athlete;

use App\Enums\AthleteGenderEnum;
use App\Rules\TemporaryFileRule;
use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class AthleteStoreRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'gender' => ['required', new Enum(AthleteGenderEnum::class)],
            'tax_number' => ['required', 'string', 'max:255', Rule::unique('athletes', 'tax_number')],
            'email' => ['required', 'email', 'max:255'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'match_records_history' => ['nullable', 'array'],
            'match_records_history.disciplines' => ['nullable', 'array'],
            'match_records_history.disciplines.*.id' => ['nullable', 'string', 'uuid', Rule::exists('disciplines', 'id')],
            'match_records_history.disciplines.*.label' => ['required', 'string', 'max:255'],
            'match_records_history.disciplines.*.manual_total' => ['required', 'integer', 'min:0'],
            'photo' => ['nullable', new TemporaryFileRule],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];
    }
}
