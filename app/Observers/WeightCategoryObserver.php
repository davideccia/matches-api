<?php

namespace App\Observers;

use App\Models\WeightCategory;

class WeightCategoryObserver
{
    public function creating(WeightCategory $weightCategory): void {}

    public function created(WeightCategory $weightCategory): void {}

    public function updating(WeightCategory $weightCategory): void {}

    public function updated(WeightCategory $weightCategory): void {}

    public function deleting(WeightCategory $weightCategory): void {}

    public function deleted(WeightCategory $weightCategory): void {}
}
