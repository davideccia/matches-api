<?php

namespace App\Http\Requests\PublicRegistrationForm;

use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationFormRegistrationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'athlete_id' => ['required', 'string', 'uuid', 'exists:athletes,id'],
            'tournament_id' => ['required', 'string', 'uuid', 'exists:tournaments,id'],
            'discipline_id' => ['required', 'string', 'uuid', 'exists:disciplines,id'],
            'weight_category_id' => ['required', 'string', 'uuid', 'exists:weight_categories,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
