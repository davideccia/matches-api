<?php

namespace App\Http\Requests\User;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('users')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', Password::min(8)],
            'superadmin' => ['sometimes', 'boolean'],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];
    }
}
