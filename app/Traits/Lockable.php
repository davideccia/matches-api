<?php

namespace App\Traits;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Lets a model run code while holding an exclusive cache lock, so two requests
 * cannot change the same data at the same time. The second request waits a few
 * seconds, then gets a 409.
 */
trait Lockable
{
    private const LOCK_TTL_SECONDS = 15;

    private const LOCK_WAIT_SECONDS = 5;

    /**
     * Keys are de-duplicated because the lock is not re-entrant: asking twice
     * for the same key would make the request wait for itself. They are sorted
     * so two requests always take locks in the same order and cannot deadlock.
     *
     * @param  Collection<int, static>  $models
     * @return list<string>
     */
    private static function lockKeysOf(Collection $models): array
    {
        return $models
            ->flatMap(fn (self $model) => $model->lockKeys())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function acquireLock(string $key): Lock
    {
        $lock = Cache::lock($key, self::LOCK_TTL_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            abort(409, __('errors.record_locked'));
        }

        return $lock;
    }

    /**
     * @param  Collection<int, static>  $models
     */
    public static function withLocks(Collection $models, Closure $callback): mixed
    {
        $locks = [];

        try {
            foreach (self::lockKeysOf($models) as $key) {
                $locks[] = self::acquireLock($key);
            }

            $models->each(fn (self $model) => $model->refreshKeepingPendingChanges());

            return $callback();
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Reloads the record from the database (a record not yet created has
     * nothing to reload), because it may have changed while
     * we waited for the lock, but keeps the changes not yet saved (e.g. the
     * payload already applied with fill()).
     */
    private function refreshKeepingPendingChanges(): void
    {
        if (! $this->exists) {
            return;
        }

        $pendingChanges = Arr::only($this->getAttributes(), array_keys($this->getDirty()));

        $this->refresh()->setRawAttributes([...$this->getAttributes(), ...$pendingChanges]);
    }

    /**
     * The keys that identify what this model locks. Override it when the
     * record must be locked through something bigger than itself.
     *
     * @return list<string>
     */
    protected function lockKeys(): array
    {
        return ["lock:{$this->getTable()}:{$this->getKey()}"];
    }

    public function withLock(Closure $callback): mixed
    {
        return static::withLocks(new Collection([$this]), $callback);
    }
}
