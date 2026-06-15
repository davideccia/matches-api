<?php

namespace App\Http\Requests\MatchRecord;

use Illuminate\Foundation\Http\FormRequest;

class MatchRecordDestroyRequest extends FormRequest
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
