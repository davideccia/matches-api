<?php

namespace App\Http\Requests\PublicRegistrationForm;

use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationFormTournamentIndexRequest extends FormRequest
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
