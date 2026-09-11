<?php

namespace App\Http\Requests\MatchRecord;

use App\Enums\MatchRecordStatusEnum;
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string'],
            'tournament_id' => ['nullable', Rule::exists('tournaments', 'id')],
            'status' => ['nullable', Rule::enum(MatchRecordStatusEnum::class)],
        ];
    }
}
