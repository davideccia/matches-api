<?php

namespace App\Http\Requests\MatchRecord;

use App\Enums\AthleteGenderEnum;
use App\Enums\MatchRecordEndMethodEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class MatchRecordStoreRequest extends FormRequest
{
    use InjectWith {
        prepareForValidation as injectWithPrepare;
    }

    protected function prepareForValidation(): void
    {
        $this->injectWithPrepare();

        $this->merge([
            'tournament_id' => $this->input('tournament_id', $this->tournament?->id),
        ]);
    }

    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'string', 'uuid', 'exists:tournaments,id'],
            'red_corner_id' => ['nullable', 'required_without:blue_corner_id', 'string', 'uuid', 'exists:athletes,id'],
            'blue_corner_id' => ['nullable', 'required_without:red_corner_id', 'string', 'uuid', 'exists:athletes,id'],
            'weight_category_id' => ['required', 'string', 'uuid', 'exists:weight_categories,id'],
            'discipline_id' => ['required', 'string', 'uuid', 'exists:disciplines,id'],
            'gender' => ['required', new Enum(AthleteGenderEnum::class)],
            'forced' => ['required', 'boolean'],
            'red_corner_team' => ['nullable', 'string', 'max:255'],
            'blue_corner_team' => ['nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'scheduled_time' => ['nullable', 'date_format:H:i:s'],
            'winner_id' => ['nullable', 'string', 'uuid', 'exists:athletes,id'],
            'end_round' => ['nullable', 'string', 'max:255'],
            'end_method' => ['nullable', new Enum(MatchRecordEndMethodEnum::class)],
            'status' => ['required', new Enum(MatchRecordStatusEnum::class)],
            'rounds' => ['required', 'integer'],
            'minutes_per_round' => ['required', 'date_format:H:i'],
            'judges_points' => ['nullable', 'array'],
            'judges_points.*.round' => ['integer'],
            'judges_points.*.judge1_red' => ['nullable', 'numeric'],
            'judges_points.*.judge2_red' => ['nullable', 'numeric'],
            'judges_points.*.judge3_red' => ['nullable', 'numeric'],
            'judges_points.*.judge1_blue' => ['nullable', 'numeric'],
            'judges_points.*.judge2_blue' => ['nullable', 'numeric'],
            'judges_points.*.judge3_blue' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([])],
        ];
    }
}
