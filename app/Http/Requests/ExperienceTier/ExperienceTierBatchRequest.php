<?php

namespace App\Http\Requests\ExperienceTier;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExperienceTierBatchRequest extends FormRequest
{
    use InjectWith {
        prepareForValidation as injectWithPrepare;
    }

    protected function prepareForValidation(): void
    {
        $this->injectWithPrepare();

        $experienceTiers = collect($this->input('experience_tiers', []))
            ->map(fn (array $tier) => array_merge($tier, [
                'tournament_id' => $tier['tournament_id'] ?? $this->tournament?->id,
            ]))
            ->all();

        $this->merge(['experience_tiers' => $experienceTiers]);
    }

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'experience_tiers' => ['required', 'array', 'min:1'],
            'experience_tiers.*.tournament_id' => ['required', 'string', 'uuid', Rule::exists('tournaments', 'id')],
            'experience_tiers.*.label' => ['required', 'string', 'max:255'],
            'experience_tiers.*.min_match_count' => ['required', 'integer', 'min:0'],
            'experience_tiers.*.max_match_count' => ['nullable', 'integer', 'min:0', 'gte:experience_tiers.*.min_match_count'],
            'experience_tiers.*.enabled' => ['sometimes', 'boolean'],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([
                'tournament',
            ])],
        ];
    }
}
