<?php

namespace App\Http\Requests\PublicRegistrationForm;

use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationFormAthleteLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_number' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
