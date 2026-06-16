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
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string'],
            'tournament_id' => ['nullable', Rule::exists('tournaments', 'id')],
            'unpaid' => ['nullable', 'boolean'],
            'unarrived' => ['nullable', 'boolean'],
            'weight_in_exceeded' => ['nullable', 'boolean'],
        ];
    }
}
