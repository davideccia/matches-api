<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class RegistrationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'athlete_id' => ['required', 'string', 'uuid', 'exists:athletes,id'],
            'tournament_id' => ['required', 'string', 'uuid', 'exists:tournaments,id'],
            'discipline_id' => ['required', 'string', 'uuid', 'exists:disciplines,id'],
            'weight_category_id' => ['required', 'string', 'uuid', 'exists:weight_categories,id'],
            'paid_at' => ['nullable', 'date'],
            'arrived' => ['required', 'boolean'],
            'weight_in' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'with' => ['nullable', 'array'],
            'with.*' => ['string'],
        ];
    }
}
