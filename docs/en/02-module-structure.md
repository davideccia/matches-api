# 02 — Module Structure

> See also: [04 — Request Lifecycle](04-request-lifecycle.md), [05 — Controllers & Requests](05-controllers-and-requests.md), [10 — Notable Patterns](10-notable-patterns.md)

This chapter is a map of the source tree. Laravel projects follow a strong directory convention, so
once you know what each folder *means*, you can navigate by intuition. Everything application-specific
lives under `app/`.

## Top-level layout

| Path | Role |
|------|------|
| `app/` | All application PHP code (models, controllers, business logic). The heart of the project. |
| `routes/` | URL → controller mappings. See [Chapter 01](01-overview.md) for the two surfaces. |
| `database/migrations/` | Versioned schema definitions (≈ ordered SQL scripts that build the tables). |
| `config/` | One file per subsystem (database, queue, broadcasting, …) reading from env vars. |
| `resources/views/` | Blade templates — here only PDFs and one email; no UI. |
| `lang/` | Translation strings: `en.json`, `it.json`. |
| `tests/` | PHPUnit tests. |
| `bootstrap/app.php` | Application startup: routing, middleware, exception rendering. |
| `.claude/skills/` | Project automation skills (e.g. `laravel-scaffold`) — tooling, not runtime code. |

## Inside `app/`

The `app/` directory is organised by **layer** (≈ responsibility), not by feature. A single feature
like "match records" is spread across many folders — a model here, a controller there, requests in
another. This is idiomatic Laravel.

```
app/
├── Actions/          stateless business-logic classes
├── Console/Commands/ artisan CLI commands (scheduled tasks)
├── Enums/            PHP enums for status/type columns
├── Events/           broadcastable events
├── Http/
│   ├── Controllers/  request handlers
│   ├── Middleware/    cross-cutting request filters
│   ├── Requests/      per-action validation + authorization
│   └── Resources/     JSON output shaping
├── Models/           Eloquent entities
│   └── Scopes/       global query scopes (one per model)
├── Notifications/    e.g. password-reset email
├── Observers/        model lifecycle hooks (one per model)
├── Providers/        service registration / bootstrapping
├── Rules/            custom validation rules
├── Support/          plain helper value objects
└── Traits/           reusable mixins (e.g. InjectWith)
```

### Layer-by-layer

**`Models/`** — Eloquent (≈ the ORM, the object↔table mapper) models. One class per table:
`Tournament`, `Athlete`, `Registration`, `MatchRecord`, `Discipline`, `WeightCategory`, `User`, plus
`Media` (file attachments). Each declares its relationships, casts, and query scopes. See
[Chapter 06](06-domain-models.md).

**`Models/Scopes/`** — one *global scope* per model (`TournamentScope`, `AthleteScope`, …). A global
scope automatically adds constraints to every query for that model (≈ a default `WHERE` clause). See
[Chapter 07](07-persistence-and-lifecycle.md). Most are currently pass-through placeholders.

**`Observers/`** — one observer per model, wired with the `#[ObservedBy(...)]` attribute (≈ a
decorator/annotation on the model class). Observers react to lifecycle events (`creating`, `updated`,
`deleting`, …). The interesting ones are `TournamentObserver` (delete guards) and
`MatchRecordObserver` (sort reordering + WebSocket broadcast). See [Chapter 07](07-persistence-and-lifecycle.md).

**`Http/Controllers/Api/`** — the request handlers. Standard CRUD resources
(`TournamentController`, `AthleteController`, …) plus nested controllers
(`TournamentRegistrationController`, `TournamentMatchRecordController`), the auth flow
(`AuthController`), the read-only `DashboardController`, and the two public controllers
(`PublicRegistrationFormController`, `PublicTournamentController`). See [Chapter 05](05-controllers-and-requests.md).

**`Http/Requests/{Model}/`** — five FormRequest classes per model (`{Model}IndexRequest`,
`ShowRequest`, `StoreRequest`, `UpdateRequest`, `DestroyRequest`). A FormRequest (≈ a validation +
authorization gate that runs *before* the controller) holds the rules for one action. See
[Chapter 05](05-controllers-and-requests.md).

**`Http/Resources/`** — API Resources (≈ output serializers/DTOs) that turn a model into its JSON
shape. Most are thin pass-throughs today (`parent::toArray()`), but they are the mandated extension
point for shaping output.

**`Enums/`** — backed PHP enums for the string columns: `TournamentStatusEnum`,
`MatchRecordStatusEnum`, `MatchRecordEndMethodEnum`, `AthleteGenderEnum`, `TournamentPdfTypeEnum`.
Each has a `label()` method returning a translated human string.

**`Actions/`** — stateless classes holding complex logic lifted out of observers/controllers. Today:
`ReorderMatchRecordsAction` (keeps the fight-card `sort` column contiguous) and
`StoreTemporaryUploadAction` (handles chunked file uploads).

**`Events/`** — `MatchRecordChanged`, the broadcastable event fired whenever a bout changes. See
[Chapter 08](08-realtime-files-and-pdf.md).

**`Traits/`** — `InjectWith` (parses the `?with=` relation-sideloading query param) and
`InteractsWithMedia` (a project wrapper over the Media Library trait). See [Chapter 10](10-notable-patterns.md).

**`Console/Commands/`** — `CleanupTemporaryUploadsCommand`, scheduled in `routes/console.php` to run
every six hours.

**`Middleware/`** — `SetLocale` (picks `en`/`it` from the `Accept-Language` header) and
`HorizonBasicAuth` (gates the Horizon dashboard).

## How a feature is distributed

To make the "spread across layers" idea concrete, here is the *single* feature **"create a match
record under a tournament"** and every file it touches:

| File | Role |
|------|------|
| `routes/api.admin.php` | Declares `POST tournaments/{tournament}/match_records`. |
| `Http/Requests/MatchRecord/MatchRecordStoreRequest.php` | Validates the body; injects `tournament_id` from the route. |
| `Http/Controllers/Api/TournamentMatchRecordController.php` | `store()` — fills and saves the model. |
| `Models/MatchRecord.php` | The entity + casts + relationships. |
| `Observers/MatchRecordObserver.php` | `creating` → reorder; `created` → broadcast. |
| `Actions/ReorderMatchRecordsAction.php` | Maintains contiguous `sort`. |
| `Events/MatchRecordChanged.php` | The broadcast payload. |
| `Http/Resources/MatchRecordResource.php` | Shapes the JSON response. |

Once you have traced this one path, every other resource follows the same shape.
