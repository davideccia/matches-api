<?php

namespace App\Http\Requests\MatchRecord;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatchRecordBulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'uuid', Rule::exists('match_records', 'id')],
        ];
    }
}
