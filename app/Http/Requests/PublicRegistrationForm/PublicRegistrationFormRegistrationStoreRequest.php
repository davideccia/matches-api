<?php

namespace App\Http\Requests\PublicRegistrationForm;

use App\Enums\AthleteGenderEnum;
use App\Enums\TournamentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PublicRegistrationFormRegistrationStoreRequest extends FormRequest
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
            'code' => ['required', 'string', 'digits:6'],

            // Always required, even when the athlete already exists: making them
            // conditional would leak whether that tax number is on file.
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'gender' => ['required', new Enum(AthleteGenderEnum::class)],
            'team_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'generic_match_records_count' => ['nullable', 'integer', 'min:0'],

            'tournament_id' => ['required', 'string', 'uuid', Rule::exists('tournaments', 'id')->where('status', TournamentStatusEnum::REGISTRATIONS_OPENED->value)],
            'discipline_id' => ['required', 'string', 'uuid', 'exists:disciplines,id'],
            'weight_category_id' => ['required', 'string', 'uuid', 'exists:weight_categories,id'],
        ];
    }
}
