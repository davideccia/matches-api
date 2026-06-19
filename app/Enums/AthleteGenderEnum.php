<?php

namespace App\Enums;

enum AthleteGenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::MALE => __('enums.athlete_gender.male'),
            self::FEMALE => __('enums.athlete_gender.female'),
            self::HYBRID => __('enums.athlete_gender.hybrid'),
        };
    }
}
