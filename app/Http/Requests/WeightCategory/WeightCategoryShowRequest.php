<?php

namespace App\Http\Requests\WeightCategory;

use Illuminate\Foundation\Http\FormRequest;

class WeightCategoryShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'with' => ['nullable', 'array'],
            'with.*' => ['string'],
        ];
    }
}
