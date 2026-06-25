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

        return [
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => ['sometimes', Password::min(8)],
            'superadmin' => ['sometimes', 'boolean'],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];
    }
}
