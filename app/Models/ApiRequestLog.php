<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit record of one API call. Deliberately holds no personal
 * data beyond the pseudonymous `user_id`: no IP, user agent, body or query
 * string, and `path` is the route template, never the concrete URL.
 */
class ApiRequestLog extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'method',
        'path',
        'route_name',
        'status',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Selects only `id, username` so staff emails never leak through it.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'username']);
    }

    #[Scope]
    public function ofUser(Builder $builder, string $userId): Builder
    {
        return $builder->where('user_id', $userId);
    }

    #[Scope]
    public function ofMethod(Builder $builder, string $method): Builder
    {
        return $builder->where('method', $method);
    }

    #[Scope]
    public function ofStatus(Builder $builder, int $status): Builder
    {
        return $builder->where('status', $status);
    }

    #[Scope]
    public function ofStatusClass(Builder $builder, int $statusClass): Builder
    {
        return $builder->whereBetween('status', [$statusClass * 100, $statusClass * 100 + 99]);
    }

    #[Scope]
    public function pathContains(Builder $builder, string $path): Builder
    {
        return $builder->whereLike('path', '%'.addcslashes($path, '%_\\').'%');
    }

    #[Scope]
    public function createdFrom(Builder $builder, CarbonInterface $from): Builder
    {
        return $builder->where('created_at', '>=', $from);
    }

    #[Scope]
    public function createdUntil(Builder $builder, CarbonInterface $until): Builder
    {
        return $builder->where('created_at', '<=', $until);
    }

    #[Scope]
    public function olderThan(Builder $builder, CarbonInterface $cutoff): Builder
    {
        return $builder->where('created_at', '<', $cutoff);
    }
}
