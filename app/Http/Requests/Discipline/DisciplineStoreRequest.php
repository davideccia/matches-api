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
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->injectWith();
    }
}
