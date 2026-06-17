<?php

namespace App\Http\Requests\TemporaryUpload;

use Illuminate\Foundation\Http\FormRequest;

class TemporaryUploadStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
