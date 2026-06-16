<?php

namespace App\Http\Requests\Discipline;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplineIndexRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
            'paginate' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string'],
        ];
    }
}
