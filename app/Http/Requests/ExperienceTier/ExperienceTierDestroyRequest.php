<?php

namespace App\Http\Requests\ExperienceTier;

use Illuminate\Foundation\Http\FormRequest;

class ExperienceTierDestroyRequest extends FormRequest
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
