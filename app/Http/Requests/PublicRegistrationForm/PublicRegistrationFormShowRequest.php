<?php

namespace App\Http\Requests\PublicRegistrationForm;

use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationFormShowRequest extends FormRequest
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
