<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $rules = [
            'username' => ['required', 'string', 'max:255', Rule::unique('users')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', Password::defaults()],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];

        if ($this->user()->superadmin) {
            $rules['superadmin'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
