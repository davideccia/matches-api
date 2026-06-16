<?php

namespace App\Http\Requests\Registration;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationStoreRequest extends FormRequest
{
    use InjectWith {
        prepareForValidation as injectWithPrepare;
    }

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
            'with.*' => [Rule::in([])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->injectWithPrepare();

        $this->merge([
            'tournament_id' => $this->input('tournament_id', $this->tournament?->id),
        ]);
    }
}
