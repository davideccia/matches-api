<?php

namespace App\Http\Requests\PublicSetting;

use Illuminate\Foundation\Http\FormRequest;

class PublicSettingLogoShowRequest extends FormRequest
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
