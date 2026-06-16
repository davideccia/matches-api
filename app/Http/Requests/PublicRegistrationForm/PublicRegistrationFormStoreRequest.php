<?php

namespace App\Http\Requests\PublicRegistrationForm;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class PublicRegistrationFormStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'gender' => ['required', new Enum(Gender::class)],
            'tax_number' => ['required', 'string', 'max:255'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'default_weight_category_id' => ['nullable', 'string', 'uuid', 'exists:weight_categories,id'],
            'default_discipline_id' => ['nullable', 'string', 'uuid', 'exists:disciplines,id'],
        ];
    }
}
