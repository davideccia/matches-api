<?php

namespace App\Http\Requests\PublicRegistrationForm;

use App\Enums\TournamentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicRegistrationFormRegistrationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'athlete_id' => ['required', 'string', 'uuid', 'exists:athletes,id'],
            'tournament_id' => ['required', 'string', 'uuid', Rule::exists('tournaments', 'id')->where('status', TournamentStatusEnum::REGISTRATIONS_OPENED->value)],
            'discipline_id' => ['required', 'string', 'uuid', 'exists:disciplines,id'],
            'weight_category_id' => ['required', 'string', 'uuid', 'exists:weight_categories,id'],
        ];
    }
}
