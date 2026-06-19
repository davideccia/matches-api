<?php

namespace App\Http\Requests\Tournament;

use App\Enums\TournamentPdfTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TournamentPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(TournamentPdfTypeEnum::class)],
        ];
    }
}
