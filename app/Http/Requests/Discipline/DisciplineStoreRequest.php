<?php

namespace App\Http\Requests\Discipline;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplineStoreRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'rounds' => ['nullable', 'numeric', 'max:255'],
            'minutes_per_round' => ['nullable', 'date_format:H:i', 'max:255'],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];
    }
}
