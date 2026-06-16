<?php

namespace App\Http\Requests\PublicRegistrationForm;

use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationFormDisciplineIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
