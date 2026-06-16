<?php

namespace App\Http\Requests\Registration;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationShowRequest extends FormRequest
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
