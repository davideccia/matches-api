<?php

namespace App\Http\Requests\ApiRequestLog;

use App\Models\ApiRequestLog;
use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiRequestLogIndexRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ApiRequestLog::class);
    }

    public function rules(): array
    {
        return [
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in(['user'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'uuid'],
            'method' => ['nullable', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])],
            'status' => ['nullable', 'integer', 'between:100,599'],
            'status_class' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'path' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
