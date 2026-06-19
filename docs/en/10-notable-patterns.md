# 10 — Notable Patterns

> See also: [05 — Controllers & Requests](05-controllers-and-requests.md), [07 — Persistence & Model Lifecycle](07-persistence-and-lifecycle.md), [04 — Request Lifecycle](04-request-lifecycle.md)

This chapter collects the recurring idioms that give the codebase its character. Recognise these and
new files become predictable; follow them when adding features so the code stays uniform.

## 1. The uniform resource pipeline

Almost every resource is the same five-method controller + five FormRequests + one Resource + one
Model + one Observer + one Scope. Adding a new resource means filling the same set of slots — not
inventing structure. The `.claude/skills/laravel-scaffold/` skill exists specifically to generate this
whole artifact set from a schema. **When in doubt, copy the shape of an existing resource.**

## 2. `InjectWith` — safe relation sideloading

The trait that powers `?with=relation1,relation2`. It lives in `app/Traits/InjectWith.php`, runs in
`prepareForValidation`, splits + camelCases the string into an array, and the request validates that
array against an **allowlist** (`with.*` → `Rule::in([...])`). The controller then eager-loads exactly
those relations. The pattern's value is that *the client controls eager loading without being able to
load arbitrary relations* — the allowlist is the security boundary. See
[Chapter 05](05-controllers-and-requests.md).

When a request needs both `InjectWith` and its own `prepareForValidation`, it aliases the trait method
(`prepareForValidation as injectWithPrepare`) and calls it first. Reuse this exact idiom rather than
duplicating the `with`-parsing logic.

## 3. `StoreRequest` ← `UpdateRequest` inheritance

When create and update share rules, the update request is a one-liner:
`class XUpdateRequest extends XStoreRequest {}`. Rules are defined once. Diverge only by overriding
`rules()` in the subclass when update genuinely differs.

## 4. Nested-route key injection

Child resources never trust a parent id in the body. The store request injects it from the route-bound
parent in `prepareForValidation`:

```php
// app/Http/Requests/Registration/RegistrationStoreRequest.php, lines 40-42
$this->merge([
    'tournament_id' => $this->input('tournament_id', $this->tournament?->id),
]);
```

This keeps `tournaments/{tournament}/registrations` consistent and unspoofable.

## 5. Observers own side effects; Actions own complex logic

Controllers stay thin. Anything triggered by persistence (delete guards, sort maintenance, real-time
broadcast) lives in the model's **observer**; anything algorithmically involved is lifted further into a
stateless **Action** class the observer calls. `MatchRecordObserver` → `ReorderMatchRecordsAction` is
the canonical example. New cross-cutting logic should follow the same split:

```
Observer (when)  →  Action (how, in a transaction)
```

## 6. "Signal, not state" broadcasting

`MatchRecordChanged` broadcasts only `{"refresh": true}` after commit, and the client re-fetches
through the normal endpoint. Combined with `ShouldDispatchAfterCommit` and a per-tournament public
channel, this keeps real-time delivery tiny, consistent, and free of duplicated serialization. Use this
shape for any future real-time resource (the `CLAUDE.md` explicitly recommends it).

## 7. Enum as a string column with a `label()`

Every status/type/method column is a `string` in the DB, cast to a backed PHP enum in the model, with
the enum exposing a translated `label()`. No DB `ENUM` types. This keeps migrations portable and
localisation centralised. See [Chapter 06](06-domain-models.md) / [Chapter 07](07-persistence-and-lifecycle.md).

## 8. Named scopes for reusable query intent

Filtering logic is expressed as `#[Scope]` methods on the model (`search`, `unpaid`, `unarrived`,
`weightInExceeded`, `inTournament`) rather than inline `where` clauses scattered across controllers. The
controller reads a validated param and calls the scope. Put new reusable query predicates here.

## 9. Aggregate in the database, not in PHP

`DashboardController` uses `withCount` with conditional closures to compute every per-tournament
statistic in a single query, instead of loading collections and counting in PHP:

```php
// app/Http/Controllers/Api/DashboardController.php, lines 20-29 (excerpt)
->withCount([
    'registrations as totalRegistrations',
    'registrations as paidRegistrations' => fn ($q) => $q->whereNotNull('paid_at'),
    'matchRecords as completedMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::COMPLETED),
    // ...
])
```

## 10. Transactions around multi-write operations

Whenever more than one row changes together — the sort reorder, or a tournament create that also
attaches a cover image — the work is wrapped in a transaction (`DB::transaction(...)` in the Action, or
explicit `DB::beginTransaction()/commit()` in `TournamentController`). This guarantees the fight card
never ends up half-reordered and a tournament is never saved without its intended media.

## 11. `saveOrFail()` over `save()`

Controllers persist with `saveOrFail()`, which throws on failure instead of returning a boolean. Paired
with the JSON exception rendering in `bootstrap/app.php`, this turns any persistence problem into a
clean JSON error rather than a silently-ignored `false`.
