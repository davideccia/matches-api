<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps the authenticated user's id into `created_user_id` / `updated_user_id`.
 *
 * Each column is written only when it exists on the model's table, and only from the server-side
 * auth state (never from input). Without an authenticated User (public flow, console, queue) the
 * stamp is null. Quiet saves and query-builder updates fire no events and are left unstamped.
 */
trait HasUserStamps
{
    public const string CREATED_USER_ID = 'created_user_id';

    public const string UPDATED_USER_ID = 'updated_user_id';

    /**
     * @var array<string, list<string>>
     */
    protected static array $userStampColumnListing = [];

    protected static function resolveUserStampId(): ?string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->getKey() : null;
    }

    public static function bootHasUserStamps(): void
    {
        static::creating(function (Model $model): void {
            $userId = static::resolveUserStampId();

            foreach ([static::CREATED_USER_ID, static::UPDATED_USER_ID] as $column) {
                if ($model->hasUserStampColumn($column) && $model->getAttribute($column) === null) {
                    $model->setAttribute($column, $userId);
                }
            }
        });

        static::updating(function (Model $model): void {
            if ($model->hasUserStampColumn(static::UPDATED_USER_ID)) {
                $model->setAttribute(static::UPDATED_USER_ID, static::resolveUserStampId());
            }
        });
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, static::CREATED_USER_ID)->select(['id', 'username']);
    }

    public function updatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, static::UPDATED_USER_ID)->select(['id', 'username']);
    }

    public function hasUserStampColumn(string $column): bool
    {
        $cacheKey = $this->getConnectionName().'.'.$this->getTable();

        static::$userStampColumnListing[$cacheKey] ??= $this->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($this->getTable());

        return in_array($column, static::$userStampColumnListing[$cacheKey], true);
    }
}
