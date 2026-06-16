<?php

namespace App\Http\Requests\WeightCategory;

use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WeightCategoryStoreRequest extends FormRequest
{
    use InjectWith;

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric'],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];
    }
}
