<?php

namespace App\Http\Requests\WeightCategory;

use Illuminate\Foundation\Http\FormRequest;

class WeightCategoryDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [];
    }
}
