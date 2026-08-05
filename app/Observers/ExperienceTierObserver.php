<?php

namespace App\Observers;

use App\Models\ExperienceTier;

class ExperienceTierObserver
{
    public function creating(ExperienceTier $experienceTier): void
    {
        abort_if($experienceTier->overlapsAnotherTier(), 400, __('errors.experience_tier_overlapping_range'));
    }

    public function created(ExperienceTier $experienceTier): void {}

    public function updating(ExperienceTier $experienceTier): void
    {
        abort_if($experienceTier->overlapsAnotherTier(), 400, __('errors.experience_tier_overlapping_range'));
    }

    public function updated(ExperienceTier $experienceTier): void {}

    public function deleting(ExperienceTier $experienceTier): void {}

    public function deleted(ExperienceTier $experienceTier): void {}
}
