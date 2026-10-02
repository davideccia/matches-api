<?php

namespace App\Http\Requests\Registration;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationIndexRequest extends FormRequest
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
            'with.*' => [Rule::in([
                'weightCategory',
                'athlete',
                'tournament',
                'discipline',
            ])],
            'paginate' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string'],
            'athlete_id' => ['nullable', 'uuid', Rule::exists('athletes', 'id')],
            'athlete_ids' => ['nullable', 'array'],
            'athlete_ids.*' => ['required', 'uuid', Rule::exists('athletes', 'id')],
            'tournament_id' => ['nullable', 'uuid', Rule::exists('tournaments', 'id')],
            'tournament_ids' => ['nullable', 'array'],
            'tournament_ids.*' => ['required', 'uuid', Rule::exists('tournaments', 'id')],
            'discipline_id' => ['nullable', 'uuid', Rule::exists('disciplines', 'id')],
            'discipline_ids' => ['nullable', 'array'],
            'discipline_ids.*' => ['required', 'uuid', Rule::exists('disciplines', 'id')],
            'weight_category_id' => ['nullable', 'uuid', Rule::exists('weight_categories', 'id')],
            'weight_category_ids' => ['nullable', 'array'],
            'weight_category_ids.*' => ['required', 'uuid', Rule::exists('weight_categories', 'id')],
            'unpaid' => ['nullable', 'boolean'],
            'unarrived' => ['nullable', 'boolean'],
            'weight_in_exceeded' => ['nullable', 'boolean'],
        ];
    }
}
