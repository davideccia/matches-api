<?php

namespace App\Http\Requests\MatchRecord;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatchRecordShowRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $this->injectWith();
    }
}
