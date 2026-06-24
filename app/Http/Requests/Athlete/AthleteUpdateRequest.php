<?php

namespace App\Http\Requests\Athlete;

use Illuminate\Validation\Rule;

class AthleteUpdateRequest extends AthleteStoreRequest
{
    public function rules(): array
    {
        $athlete = $this->route('athlete');

        return [
            ...parent::rules(),
            'tax_number' => Rule::unique('athletes', 'tax_number')->ignore($athlete),
        ];
    }
}
