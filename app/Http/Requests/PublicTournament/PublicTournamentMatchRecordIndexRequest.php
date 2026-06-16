<?php

namespace App\Http\Requests\PublicTournament;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicTournamentMatchRecordIndexRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return true;
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
        ];
    }
}
