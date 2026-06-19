# 05 — Controllers & Requests

> See also: [04 — Request Lifecycle](04-request-lifecycle.md), [10 — Notable Patterns](10-notable-patterns.md), [06 — Domain Models](06-domain-models.md)

This chapter covers the HTTP-handling layer: how controllers are structured, the strict
five-FormRequest-per-model convention, and the cross-cutting query features (`?with=`, pagination,
search) that every list/detail endpoint shares.

## Controller catalogue

| Controller | Surface | Notes |
|------------|---------|-------|
| `TournamentController` | admin | Full CRUD `apiResource`. |
| `AthleteController` | admin | Full CRUD. |
| `RegistrationController` | admin | Full CRUD + `pdf()` action. |
| `MatchRecordController` | admin | Full CRUD. |
| `DisciplineController`, `WeightCategoryController` | admin + public | Reference data; `index` also exposed publicly. |
| `UserController` | admin | Organiser-account CRUD. |
| `TournamentRegistrationController` | admin | Nested: `index` + `store` under a tournament. |
| `TournamentMatchRecordController` | admin | Nested: `index` + `store` + `matchRecordsPdf`. |
| `AuthController` | admin | `login`, `user`, `logout`, `forgotPassword`, `resetPassword`. |
| `DashboardController` | admin | Read-only aggregate counts per active tournament. |
| `PublicRegistrationFormController` | public | Athlete lookup/upsert, open-tournament list, self-registration, registration PDF. |
| `PublicTournamentController` | public | Public tournament list + public fight card. |
| `TemporaryUploadController`, `MediaController` | admin | Chunked uploads and media download. |

`apiResource` (a Laravel route helper) maps the five REST verbs to the five controller methods:
`index` (GET list), `store` (POST create), `show` (GET one), `update` (PUT/PATCH), `destroy` (DELETE).

## The five-FormRequest convention

Every model has **exactly five** FormRequest classes under `app/Http/Requests/{Model}/`, one per
action: `{Model}IndexRequest`, `{Model}ShowRequest`, `{Model}StoreRequest`, `{Model}UpdateRequest`,
`{Model}DestroyRequest`. A FormRequest bundles **authorization** (`authorize()`) and **validation**
(`rules()`) for a single action, and runs automatically before the controller (see
[Chapter 04](04-request-lifecycle.md)).

A consistent shortcut: **`UpdateRequest` extends `StoreRequest`** when the rules are identical, so the
update rules are defined once:

```php
// app/Http/Requests/MatchRecord/MatchRecordUpdateRequest.php (entire file)
class MatchRecordUpdateRequest extends MatchRecordStoreRequest {}
```

Authorization is intentionally simple at this layer: admin requests use `return auth()->hasUser();`
(any authenticated organiser may act); fine-grained rules, where they exist, live in the
domain/observer layer instead.

## Cross-cutting query features

Every index/show/store request supports the same three families of query parameters. They are the
"API ergonomics" of the project.

### Relation sideloading — `?with=`

Clients choose which related records to embed via a comma-separated `with` parameter, e.g.
`?with=redCorner,blueCorner,winner`. The `InjectWith` trait normalises it:

```php
// app/Traits/InjectWith.php, lines 9-16
protected function prepareForValidation(): void
{
    $with = collect($this->with ? explode(',', $this->with) : [])
        ->map(fn ($w) => Str::camel($w))
        ->all();

    $this->merge(['with' => $with]);
}
```

The string is split, each name is camelCased, and the resulting array is validated against an
**allowlist** in the request's `with.*` rule:

```php
// app/Http/Requests/MatchRecord/MatchRecordIndexRequest.php, lines 21-29
'with' => ['nullable', 'array'],
'with.*' => [Rule::in([
    'tournament', 'redCorner', 'blueCorner', 'winner', 'weightCategory', 'discipline',
])],
```

The controller then eager-loads exactly those relations with `->with($validated['with'] ?? [])` (lists)
or `->loadMissing($validated['with'] ?? [])` (single records). The allowlist is the security boundary
— a client cannot load an arbitrary relation. Note that **store/update** requests often allow *no*
relations (`Rule::in([])`), so `?with=` is mainly an index/show feature.

When a request both uses `InjectWith` *and* needs its own `prepareForValidation` (to inject a route
key), it aliases the trait method and calls it explicitly:

```php
// app/Http/Requests/MatchRecord/MatchRecordStoreRequest.php, lines 15-17, 50-57
use InjectWith {
    prepareForValidation as injectWithPrepare;
}
// ...
protected function prepareForValidation(): void
{
    $this->injectWithPrepare();
    $this->merge(['tournament_id' => $this->input('tournament_id', $this->tournament?->id)]);
}
```

### Pagination

Index requests expose `paginate` (bool), `per_page`, and `page`. The controller branches on it:

```php
// app/Http/Controllers/Api/TournamentController.php, lines 28-32
if ($validated['paginate'] ?? false) {
    $tournaments = $tournaments->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
} else {
    $tournaments = $tournaments->get();
}
```

`paginate=true` returns a paginated envelope (data + meta); otherwise the full collection.

### Search

A nullable `search` string triggers the model's `search` query scope (see
[Chapter 07](07-persistence-and-lifecycle.md)), which does case-insensitive `ilike` matching across the
relevant columns/relations:

```php
// app/Http/Controllers/Api/TournamentController.php, lines 24-26
if (isset($validated['search'])) {
    $tournaments->search($validated['search']);
}
```

## Nested-route injection

For nested routes like `tournaments/{tournament}/match_records`, the parent id is **not** in the
request body — it comes from the URL. The store request injects it in `prepareForValidation` (shown
above) by reading `$this->tournament` (the route-bound model), so the client never has to send a
redundant `tournament_id` and cannot spoof a different one.

## Two non-CRUD controllers worth knowing

- **`AuthController`** — `login()` verifies credentials with `auth()->attempt()` then issues a Sanctum
  token via `$user->createToken('api')->plainTextToken`; `logout()` deletes the current token;
  `forgotPassword`/`resetPassword` use Laravel's `Password` broker and email a reset link built from
  `app.frontend_url`.
- **`DashboardController`** — a single read-only `index()` that returns active tournaments with
  `withCount` aggregates (registrations total/arrived/paid, matches by status). It is the one place
  using conditional count subqueries, and a good example of pushing aggregation into the database
  rather than counting in PHP.
