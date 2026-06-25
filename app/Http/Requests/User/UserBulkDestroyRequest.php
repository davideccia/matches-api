<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserBulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkDestroy', User::class);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'uuid', Rule::exists('users', 'id'), Rule::notIn([$this->user()->id])],
        ];
    }
}
