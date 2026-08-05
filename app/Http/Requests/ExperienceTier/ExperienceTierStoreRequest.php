<?php

namespace App\Http\Requests\ExperienceTier;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExperienceTierStoreRequest extends FormRequest
{
    use InjectWith {
        prepareForValidation as injectWithPrepare;
    }

    protected function prepareForValidation(): void
    {
        $this->injectWithPrepare();

        $this->merge([
            'tournament_id' => $this->input('tournament_id', $this->tournament?->id),
        ]);
    }

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['nullable', 'string', 'uuid', Rule::exists('tournaments', 'id')],
            'label' => ['required', 'string', 'max:255'],
            'min_match_count' => ['required', 'integer', 'min:0'],
            'max_match_count' => ['nullable', 'integer', 'min:0', 'gte:min_match_count'],
            'enabled' => ['sometimes', 'boolean'],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([
                'tournament',
            ])],
        ];
    }
}
