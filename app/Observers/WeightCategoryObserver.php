<?php

namespace App\Observers;

use App\Models\WeightCategory;
use Spatie\ResponseCache\Facades\ResponseCache;

class WeightCategoryObserver
{
    public static function saved(WeightCategory $weightCategory): void
    {
        ResponseCache::clear(['public-weight-categories']);
    }

    public function creating(WeightCategory $weightCategory): void {}

    public function created(WeightCategory $weightCategory): void {}

    public function updating(WeightCategory $weightCategory): void {}

    public function updated(WeightCategory $weightCategory): void {}

    public function deleting(WeightCategory $weightCategory): void
    {
        abort_if($weightCategory->registrations()->exists(), 409, __('errors.weight_category_has_registrations'));
        abort_if($weightCategory->matchRecords()->exists(), 409, __('errors.weight_category_has_match_records'));
    }

    public function deleted(WeightCategory $weightCategory): void
    {
        ResponseCache::clear(['public-weight-categories']);
    }
}
