<?php

namespace App\Http\Requests\WeightCategory;

use Illuminate\Foundation\Http\FormRequest;

class WeightCategoryStoreRequest extends FormRequest
{
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
            'with.*' => ['string'],
        ];
    }
}
