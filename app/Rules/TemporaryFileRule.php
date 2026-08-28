<?php

namespace App\Rules;

use App\Support\TemporaryFile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class TemporaryFileRule implements ValidationRule
{
    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || TemporaryFile::find($value) === null) {
            $fail(trans('validation.temporary_file_rule'));
        }
    }
}
