<?php

namespace App\Http\Requests\Tournament;

use App\Enums\TournamentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TournamentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location_name' => ['required', 'string', 'max:255'],
            'location_address' => ['required', 'string', 'max:255'],
            'location_city' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'status' => ['required', new Enum(TournamentStatus::class)],
            'with' => ['nullable', 'array'],
            'with.*' => ['string'],
        ];
    }
}
