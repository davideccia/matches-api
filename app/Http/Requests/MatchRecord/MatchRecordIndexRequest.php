<?php

namespace App\Http\Requests\MatchRecord;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatchRecordIndexRequest extends FormRequest
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
                'tournament',
                'redCorner',
                'blueCorner',
                'winner',
                'weightCategory',
                'discipline',
            ])],
            'paginate' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string'],
            'tournament_id' => ['nullable', Rule::exists('tournaments', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->injectWith();
    }
}
