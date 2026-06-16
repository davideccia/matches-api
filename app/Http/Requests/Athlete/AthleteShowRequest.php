<?php

namespace App\Http\Requests\Athlete;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AthleteShowRequest extends FormRequest
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
        ];
    }
}
