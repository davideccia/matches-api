<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class SettingLogoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:5120'],
        ];
    }
}
