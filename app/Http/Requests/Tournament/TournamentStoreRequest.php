<?php

namespace App\Http\Requests\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Rules\TemporaryFileRule;
use App\Traits\InjectWith;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class TournamentStoreRequest extends FormRequest
{
    use InjectWith;

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
            'status' => ['required', new Enum(TournamentStatusEnum::class)],
            'cover' => ['nullable', new TemporaryFileRule],
            'disciplines' => ['nullable', 'array'],
            'disciplines.*' => ['required', 'string', 'uuid', Rule::exists('disciplines', 'id')],
            'with' => ['nullable', 'array'],
            'with.*' => [Rule::in([
                'coverMedia',
                'experienceTiers',
                'disciplines',
            ])],
        ];
    }
}
