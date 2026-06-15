<?php

namespace App\Http\Requests\Discipline;

use Illuminate\Foundation\Http\FormRequest;

class DisciplineStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'with' => ['nullable', 'array'],
            'with.*' => ['string'],
        ];
    }
}
