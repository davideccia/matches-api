<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    public function rules(): array
    {
        return [
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
            'paginate' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string'],
        ];
    }
}
