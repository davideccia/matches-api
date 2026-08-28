<?php

namespace App\Http\Requests\User;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends UserStoreRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $user = $this->route('user');

        $rules = [
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => ['sometimes', Password::defaults()],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];

        if ($this->user()->superadmin) {
            $rules['superadmin'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
