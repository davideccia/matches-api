<?php

namespace App\Http\Requests\Discipline;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplineBulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'uuid', Rule::exists('disciplines', 'id')],
        ];
    }
}
