<?php

namespace App\Http\Requests\PublicTournament;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicTournamentCurrentMatchRecordsRequest extends FormRequest
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
        ];
    }
}
