<?php

namespace App\Models;

use App\Models\Scopes\WeightCategoryScope;
use App\Observers\WeightCategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([WeightCategoryObserver::class])]
#[ScopedBy([WeightCategoryScope::class])]
class WeightCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'label',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder;
    }
}
