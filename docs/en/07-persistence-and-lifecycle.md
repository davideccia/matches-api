# 07 — Persistence & Model Lifecycle

> See also: [06 — Domain Models](06-domain-models.md), [04 — Request Lifecycle](04-request-lifecycle.md), [10 — Notable Patterns](10-notable-patterns.md)

This chapter covers how data is stored and how the application reacts to model changes: UUID keys,
migrations, casts, **observers** (lifecycle hooks), and **global scopes** (default query constraints).

## Eloquent in one paragraph

**Eloquent** is Laravel's ORM (≈ object-relational mapper — the layer that maps a database row to a
PHP object). Each table has a model class; `Model::find($id)` loads a row, `$model->save()` persists
changes, and relationship methods (`hasMany`, `belongsTo`) express foreign keys. You rarely write SQL
directly — you compose a query through the fluent builder (`Tournament::where(...)->orderBy(...)->get()`).

## UUID primary keys everywhere

A hard invariant: **all primary and foreign keys are UUIDs**, not auto-increment integers. Models
opt in with the `HasUuids` trait, and migrations declare `uuid('id')->primary()` and
`foreignUuid(...)->constrained(...)`:

```php
// database/migrations/2026_06_15_100006_create_match_records_table.php, lines 12-17
$table->uuid('id')->primary();
$table->foreignUuid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
$table->foreignUuid('red_corner_id')->constrained('athletes')->cascadeOnDelete();
$table->foreignUuid('blue_corner_id')->constrained('athletes')->cascadeOnDelete();
$table->foreignUuid('weight_category_id')->constrained('weight_categories')->cascadeOnDelete();
$table->foreignUuid('discipline_id')->constrained('disciplines')->cascadeOnDelete();
```

Because UUID foreign keys arrive from JSON as strings, the models also cast id columns to `'string'`
(see `MatchRecord::casts()`), keeping types predictable.

## Casts — typing the columns

A model's `casts()` method declares how raw DB values become PHP values. Three cast styles matter
here:

| Cast | Effect |
|------|--------|
| `'date'` / `'datetime'` | Carbon date objects (≈ a rich DateTime). |
| `'boolean'` / `'float'` | Native scalar types. |
| `EnumClass::class` | A backed PHP enum (the enum-stored-as-string rule). |
| `'array'` | JSON column ↔ PHP array (e.g. `judges_points`). |

```php
// app/Models/MatchRecord.php, lines 45-60 (excerpt)
'gender' => AthleteGenderEnum::class,
'end_method' => MatchRecordEndMethodEnum::class,
'status' => MatchRecordStatusEnum::class,
'judges_points' => 'array',
```

## Migrations

Migrations (`database/migrations/`) are ordered, versioned schema scripts — each has `up()` (apply)
and `down()` (roll back). The filename timestamp sets the order. Foreign keys use cascade rules:
`cascadeOnDelete()` (delete children with the parent) for the corners and references, but
`nullOnDelete()` for `winner_id` (deleting an athlete clears the winner pointer rather than erasing the
bout). Run them with `vendor/bin/sail artisan migrate`.

## Observers — reacting to model lifecycle

An **observer** is a class whose methods fire on a model's lifecycle events: `creating`/`created`,
`updating`/`updated`, `deleting`/`deleted`. The `*ing` variants run *before* the DB write (and can
abort or mutate it); the `*ed` variants run *after*. Each model is bound to its observer with an
attribute:

```php
// app/Models/Tournament.php, line 19
#[ObservedBy([TournamentObserver::class])]
```

Most observers are scaffolded with empty methods. Two carry real logic.

### TournamentObserver — delete guards

Prevents deleting a tournament that still has dependent data, returning HTTP `409 Conflict` with a
translated message:

```php
// app/Observers/TournamentObserver.php, lines 17-21
public function deleting(Tournament $tournament): void
{
    abort_if($tournament->registrations()->exists(), 409, __('errors.tournament_has_registrations'));
    abort_if($tournament->matchRecords()->exists(), 409, __('errors.tournament_has_match_records'));
}
```

### MatchRecordObserver — sort + broadcast

The busiest observer. It delegates `sort` maintenance to an Action and emits the real-time event:

```php
// app/Observers/MatchRecordObserver.php, lines 11-39 (excerpt)
public function creating(MatchRecord $matchRecord): void  { ReorderMatchRecordsAction::handleCreating($matchRecord); }
public function created(MatchRecord $matchRecord): void   { event(new MatchRecordChanged($matchRecord)); }
public function updating(MatchRecord $matchRecord): void  { ReorderMatchRecordsAction::handleUpdating($matchRecord); }
public function updated(MatchRecord $matchRecord): void   { event(new MatchRecordChanged($matchRecord)); }
public function deleting(MatchRecord $matchRecord): void  { ReorderMatchRecordsAction::handleDeleting($matchRecord); }
public function deleted(MatchRecord $matchRecord): void   { event(new MatchRecordChanged($matchRecord)); }
```

### The sort-reorder Action

`ReorderMatchRecordsAction` keeps each tournament's fight card a **contiguous** `1..N` sequence as
bouts are inserted, moved, or removed — all inside DB transactions so the card never ends up with gaps
or duplicate positions. On insert, if no position (or one beyond the end) is requested it appends;
otherwise it shifts everything at/after the target up by one:

```php
// app/Actions/ReorderMatchRecordsAction.php, lines 18-29 (excerpt)
if ($requestedSort === null || $requestedSort > $max) {
    $matchRecord->sort = $max + 1;
    return;
}
MatchRecord::withoutGlobalScopes([MatchRecordScope::class])
    ->where('tournament_id', $tournamentId)
    ->where('sort', '>=', $requestedSort)
    ->increment('sort');
$matchRecord->sort = $requestedSort;
```

Update handling shifts the in-between rows in whichever direction the bout moved; delete handling
closes the gap by decrementing everything after the removed position. Note the deliberate
`withoutGlobalScopes(...)` — the reorder must see *every* row in the tournament, not a scoped subset.

## Global scopes — default query constraints

A **global scope** automatically adds a constraint to *every* query for a model (≈ a default `WHERE`
applied unless explicitly removed). Each model is bound to one via `#[ScopedBy([...])]`. In this
codebase the scopes are currently **pass-through placeholders** — they inspect the authenticated user
but add no constraint yet:

```php
// app/Models/Scopes/TournamentScope.php, lines 11-18
public function apply(Builder $builder, Model $model): void
{
    $user = auth()->user();
    if ($user === null) {
        return;
    }
}
```

They exist as the designated hook for future multi-tenancy or visibility rules. The important
practical consequence is already visible in the reorder Action: when logic must bypass these scopes, it
calls `withoutGlobalScopes([...])`.

## Named query scopes

Separately from *global* scopes, models define **named** scopes with the `#[Scope]` attribute —
reusable query fragments you opt into. The `search` scope (on nearly every model) powers the `?search=`
parameter; `Registration` adds `unpaid`, `unarrived`, `weightInExceeded`; `Athlete` adds `inTournament`.
See [Chapter 06](06-domain-models.md) for what each one does.
