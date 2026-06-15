<?php

namespace App\Http\Requests\Athlete;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AthleteIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'with' => ['nullable', 'array'],
            'with.*' => ['string'],
            'paginate' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'tournament_id' => ['nullable', Rule::exists('tournaments', 'id')],
            'discipline_id' => ['nullable', Rule::exists('disciplines', 'id')],
            'weight_category_id' => ['nullable', Rule::exists('weight_categories', 'id')],
        ];
    }
}
