<?php

namespace App\Http\Requests\Athlete;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AthleteIndexRequest extends FormRequest
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
            'tournament_id' => ['nullable', Rule::exists('tournaments', 'id')],
            'discipline_id' => ['nullable', Rule::exists('disciplines', 'id')],
            'weight_category_id' => ['nullable', Rule::exists('weight_categories', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->injectWith();
    }
}
