<?php

namespace App\Http\Requests\MatchRecord;

use App\Enums\EndMethod;
use App\Enums\Gender;
use App\Enums\MatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MatchRecordStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'string', 'uuid', 'exists:tournaments,id'],
            'red_corner_id' => ['required', 'string', 'uuid', 'exists:athletes,id'],
            'blue_corner_id' => ['required', 'string', 'uuid', 'exists:athletes,id'],
            'weight_category_id' => ['required', 'string', 'uuid', 'exists:weight_categories,id'],
            'discipline_id' => ['required', 'string', 'uuid', 'exists:disciplines,id'],
            'gender' => ['required', new Enum(Gender::class)],
            'forced' => ['required', 'boolean'],
            'red_corner_team' => ['required', 'string', 'max:255'],
            'blue_corner_team' => ['required', 'string', 'max:255'],
            'sort' => ['required', 'integer'],
            'scheduled_time' => ['nullable', 'date_format:H:i:s'],
            'winner_id' => ['nullable', 'string', 'uuid', 'exists:athletes,id'],
            'end_round' => ['nullable', 'string', 'max:255'],
            'end_method' => ['nullable', new Enum(EndMethod::class)],
            'status' => ['required', new Enum(MatchStatus::class)],
            'rounds' => ['required', 'integer'],
            'minutes_per_round' => ['required', 'numeric'],
            'judges_points' => ['nullable', 'array'],
            'with' => ['nullable', 'array'],
            'with.*' => ['string'],
        ];
    }
}
