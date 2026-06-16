<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait InjectWith
{
    protected function prepareForValidation(): void
    {
        $with = collect($this->with ? explode(',', $this->with) : [])
            ->map(fn ($w) => Str::camel($w))
            ->all();

        $this->merge(['with' => $with]);
    }
}
