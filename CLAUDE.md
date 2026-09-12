# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Before Writing Any Code

You MUST invoke the `/laravel-best-practices` skill before analyzing or writing any code. Do not skip this step, even
for small changes.

## Project Overview

This is a **Laravel 13 REST API** backend for a combat-sports tournament domain. There is no SPA/frontend; the only
Blade views are server-rendered PDF templates (`resources/views/pdf/`) and email templates (`resources/views/emails/`).
Auth is handled via Laravel Sanctum.

Local development runs via Laravel Sail: **PostgreSQL** for the database, **Redis** for cache (and sessions/queues), and
**Mailpit** for outgoing email. Queues run on Laravel Horizon; real-time broadcasting uses Laravel Reverb.

## Domain Model

Combat sports tournament management. Core entities and their relationships:

- **Tournament** → has many Registrations, MatchRecords, ExperienceTiers; status flows through `TournamentStatusEnum`;
  carries a denormalized `matchmaking_issues` JSON column
- **Athlete** → has many Registrations, MatchRecords (as red/blue corner or winner); looked up publicly by `tax_number`;
  gender via `AthleteGenderEnum`
- **Registration** — join of Athlete + Tournament + Discipline + WeightCategory; tracks `paid_at`, `arrived`,
  `weight_in`
- **MatchRecord** — a bout between two athletes; has `sort` (ordered within tournament), `status`
  (`MatchRecordStatusEnum`), `end_method` (`MatchRecordEndMethodEnum`), `judges_points` (JSON array)
- **ExperienceTier** — a named `[min_match_count, max_match_count]` band (`max` nullable = open-ended) used to bucket
  athletes during matchmaking. `tournament_id` is **nullable**: a null row is a *global* tier, a non-null row is a
  tournament override. Only `enabled` tiers participate.
- **Discipline** and **WeightCategory** are reference data shared across tournaments

**Athlete match history** is tracked per discipline in the `match_records_history` JSON column: `{"total": int,
"disciplines": [{"id": ?uuid, "label": string, "manual_total": int, "app_total": int, "total": int}]}`. `manual_total`
is entered by hand (prior-fight history) and is the only client-writable sub-field; `app_total` is recomputed from
completed `MatchRecord`s by `Athlete::syncMatchRecordsHistory()` (never accepted from client input) and `total` =
`manual_total + app_total` per discipline; the root `total` sums every discipline's `total`. There is no appended
`match_records_count` attribute — read `match_records_history['total']` directly. `Athlete` `$appends` are only `age`
and `is_adult` (birth_date-derived, 18+). DB-level filtering uses the `minMatchRecordsCount`/`maxMatchRecordsCount`
scopes (raw SQL over the JSON column's root `total`). Matchmaking uses `Athlete::matchRecordsCountForDiscipline()` to
bucket an athlete by their experience in the specific discipline being contested, not their combined total.

Enums live in `app/Enums/` and are suffixed `...Enum` (e.g. `TournamentPdfTypeEnum`).

## Architecture Invariants

- **All primary and foreign key IDs are UUIDs** — enforced by the `laravel-scaffold` skill and must be reflected in
  migrations and models.
- **PHP Enum columns are stored as strings in the database** — cast to a PHP-backed Enum in the model. Never use a
  DB-level ENUM type.
- **Routes are split by audience, not version** — `bootstrap/app.php` mounts `routes/api.admin.php` under the
  `/api/admin/` prefix and `routes/api.public.php` under `/api/public/` (the latter with a global `throttle:10,1`). A
  `SetLocale` middleware is prepended to the `api` group. There is no `/api/v1/` prefix.
- **Eloquent API Resources are mandatory** for all API responses. Public (unauthenticated) endpoints use a separate
  namespace: `app/Http/Resources/Public/Public{Model}Resource.php`.
- **Two distinct scope mechanisms.** *Global* scopes are dedicated classes in `app/Models/Scopes/` (e.g. `AthleteScope`)
  attached with the `#[ScopedBy([...])]` attribute — every model has one, but they are currently **no-op placeholders**
  (they early-return when there is no authed user and add no constraints). *Local* scopes are methods on the model
  annotated with `#[Scope]` (e.g. `Athlete::search()`, `Athlete::adult()`, `ExperienceTier::enabled()`) — put reusable
  query filters there rather than inline in controllers. Queries that must bypass global scopes use
  `withoutGlobalScopes()` (see `MatchmakingService`).
- Use `.claude/skills/laravel-scaffold/` to generate a full artifact set (migration, model, observer, scopes, resource,
  controller, form requests, seeder) from a DBML schema.

## Route Files

`routes/api.php` is empty; the prefixes/groups are wired in `bootstrap/app.php` (see above). Routes live in:

- `routes/api.admin.php` (`/api/admin/`) — `auth/*` (login/logout/forgot/reset/user), `dashboard`, all `apiResource`s
  (users, weight_categories, disciplines, experience_tiers, athletes, tournaments, registrations, match_records), nested
  `tournaments.{registrations,match_records,experience_tiers}` (index/store only), `temporary_uploads`, PDF endpoints,
  and `DELETE {resource}/bulk` routes (preceding each `apiResource` so they aren't shadowed). Everything except the
  `auth/*` entry points is behind `auth:sanctum`. `auth/login` uses `throttle:auth-login`,
  `auth/forgot_password` + `auth/reset_password` use `throttle:auth-password-reset`; `auth/user` at `throttle:10,1`.
- `routes/api.public.php` (`/api/public/`) — unauthenticated. `registration_form/*` (`POST athletes/lookup`,
  `POST verification_code`, `POST registrations`, tournaments/disciplines/weight_categories index, and a **signed**
  `GET registrations/{registration}/pdf`) and `tournaments/*` (public tournament list + match records).

**Rate limiters are named, not inline.** `AppServiceProvider::rateLimiting()` defines `auth-login`,
`auth-password-reset` (both 5/min keyed *by IP and by email* — per-account, not just per-IP), `public-athlete-lookup`
and `public-verification-code` (5/min per IP). They stack on top of the group-level `throttle:10,1` while keeping their
own counter, which an unnamed `throttle:5,1` would not. Add new limits there, not as inline `throttle:n,m`.

**Custom actions** (not part of the standard CRUD resource) must be declared *before* the matching `apiResource` so the
resource's `{id}` wildcard doesn't shadow them:

- `GET tournaments/{tournament}/match_records/pdf` — `TournamentMatchRecordController::matchRecordsPdf`
- `POST tournaments/{tournament}/match_records/generate` — `TournamentMatchRecordController::generateMatchRecords`
  (triggers `Tournament::runMatchmaking()`)
- `GET registrations/{registration}/pdf` — `RegistrationController::pdf`

Nested-route controllers are separate classes named `Tournament{Child}Controller` (`TournamentRegistrationController`,
`TournamentMatchRecordController`, `TournamentExperienceTierController`), not extra methods on the child's own
controller.

## Public Registration Flow

The public registration form is a three-step, email-verified flow — it is not a plain `POST /registrations`:

1. `POST registration_form/athletes/lookup` — tax_number **plus email**; both must match an existing athlete
   (`Athlete::normalizeTaxNumber()` / `emailMatches()`), otherwise a generic 400 (`errors.athlete_lookup_failed`) and a
   `public.athlete_lookup_failed` log line. Never reveal which half was wrong.
2. `POST registration_form/verification_code` — `App\Support\RegistrationVerificationCode::issue()` mails a 6-digit
   code (`RegistrationVerificationCodeNotification`) and caches only its sha256 hash for 10 minutes. An **existing**
   athlete is always contacted at the address on file, never the caller-supplied one. Max 3 issues per 15 min per tax
   number, and the endpoint always answers 204 so it cannot be used as an enumeration oracle.
3. `POST registration_form/registrations` — `RegistrationVerificationCode::verify()` first (5 wrong guesses burn the
   code; a wrong guess never refreshes the TTL). An existing athlete's record is **never overwritten** by the payload —
   that would make this endpoint an account takeover. `privacy_accepted_at` is stamped server-side with `now()` at
   creation (never trusted from client input) and is then immutable — `RegistrationObserver::updating` aborts with 400
   (`errors.registration_privacy_accepted_at_immutable`) if a later update tries to change it.

## Request / Controller Patterns

Every resource has five FormRequests named `{Model}{Action}Request` (e.g., `MatchRecordStoreRequest`), stored in
`app/Http/Requests/{Model}/`. There is also a sixth `{Model}BulkDestroyRequest` pattern for bulk deletes (accepts
`ids: uuid[]`).

**`InjectWith` trait** (`app/Traits/InjectWith.php`): all index/show/store requests use this trait. Clients pass
`?with=relation1,relation2` as a comma-separated string; the trait converts it to a camelCase array that is then
validated against an allowlist in `with.*` rules. Controllers call `$model->loadMissing($validated['with'] ?? [])` to
eager-load.

**Pagination**: index requests expose `paginate` (bool), `per_page`, `page` params. When `paginate=true`, the controller
calls `->paginate()`; otherwise `->get()`.

**Nested route injection**: for nested routes like `tournaments/{tournament}/match_records`, the store request's
`prepareForValidation` injects `tournament_id` from the route-bound model.

## Observers

Each model has an observer (`app/Observers/`) wired via `#[ObservedBy]` attribute. Key responsibilities:

- **Delete guards**: `TournamentObserver::deleting` aborts with 409 if related registrations or match_records exist
- **Sort management**: `MatchRecordObserver` delegates creating/updating/deleting to `ReorderMatchRecordsAction` to
  maintain contiguous `sort` ordering per tournament
- **WebSocket broadcast**: `MatchRecordObserver` fires `MatchRecordChanged` event on every CRUD operation
- **Denormalized counters**: `MatchRecordObserver::saved`/`deleted` re-sync `Tournament::syncMatchmakingIssues()` and
  `Athlete::syncMatchRecordsHistory()` for both corners. These use `saveQuietly()` to avoid observer recursion — keep it
  that way when adding similar sync logic.
- **Validation guards**: `ExperienceTierObserver::creating`/`updating` abort with 400
  (`errors.experience_tier_overlapping_range`) when `ExperienceTier::overlapsAnotherTier()` finds an enabled tier whose
  range intersects, scoped to the same `tournament_id` (globals only collide with globals).

## Events & Broadcasting

`MatchRecordChanged` (`app/Events/`) implements `ShouldBroadcast` and `ShouldDispatchAfterCommit`. It broadcasts a
`{"refresh": true}` payload on the channel `tournaments.{tournament_id}.match_records`. Use this pattern when real-time
push is needed for future resources.

## Actions

`app/Actions/` holds stateless classes for complex business logic extracted from observers/controllers:

- `ReorderMatchRecordsAction` — handles insert/update/delete of the `sort` column across a tournament's match records
  within DB transactions.
- `StoreTemporaryUploadAction` — backs `POST temporary_uploads` (chunked/temporary file uploads later attached to a
  model's media).

## Matchmaking

`app/Services/MatchmakingService.php` is the only service class. It is constructed with a `Tournament` and backs two
`Tournament` methods:

- `runMatchmaking()` — builds the fight card from the tournament's registrations inside a DB transaction, then stores
  the leftovers into `matchmaking_issues`. Exposed over HTTP by `POST tournaments/{tournament}/match_records/generate`.
- `syncMatchmakingIssues()` — recomputes `matchmaking_issues` alone (called from `MatchRecordObserver` whenever the card
  changes).

**Pairing key.** Registrations whose athlete is already paired in this tournament are excluded, then the rest are
grouped by `discipline_id | weight_category_id | gender | adult-or-minor` and, within each group, sub-bucketed by
**experience tier label**. Each bucket is chunked in twos; the first of a pair is the red corner. All five dimensions
must match for two athletes to be paired.

**Tier resolution** (`MatchmakingService::tiers()`): if the tournament has any `enabled` tiers of its own they **replace
the global set entirely** (not merged); otherwise the enabled `tournament_id IS NULL` globals are used. Tiers are matched
against the athlete's `matchRecordsCountForDiscipline()` for the registration's own discipline, not their combined total
across all disciplines. An athlete whose per-discipline count falls in no tier is never paired.

**`matchmaking_issues`** is a JSON array of unpaired registrations, each with a `reason`: `no_tier` (no matching
experience tier) or `unpaired` (odd one out in its bucket). It is computed two ways depending on entry point — from
in-memory leftovers after a matchmaking run, or re-derived from existing match records via
`resolveIssuesFromMatchRecords()`.

## Authorization

Only the `users` resource is policy-guarded: each `UserRequest::authorize()` calls the corresponding `UserPolicy`
ability (`create`/`update`/`delete`/`bulkDestroy` gate on `$user->superadmin`). All other resources return `true` from
`authorize()` and rely on `auth:sanctum` alone. An `ability` middleware alias (Sanctum's `CheckForAnyAbility`) is
registered in `bootstrap/app.php` but deliberately unused: every token is issued with the default `['*']` abilities
because all admin users are trusted staff. The alias stays wired so scoping a future token type is a route change, not
a setup. (The `docs/security-issues/SPECS.md` referenced by that comment is **not in this repo**.)

Horizon's dashboard and Log Viewer are behind `HorizonBasicAuth` / `LogViewerBasicAuth`, both thin subclasses of the
abstract `App\Http\Middleware\BasicAuth` (credentials read from `config('horizon.basic_auth_*')` /
`config('log-viewer.basic_auth_*')`). Username and password are compared with `hash_equals` over sha256 and **both
comparisons always run** — `&&` would short-circuit and leak, through timing, which half was correct. Keep that shape.
The `viewHorizon` gate itself is open.

`App\Http\Middleware\SecurityHeaders` is appended to *both* the `api` and `web` groups and sets
`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`.

`AppServiceProvider::definePasswordDefaults()` sets `Password::min(12)` (length over composition rules, per NIST
800-63B) and adds `->uncompromised()` **only in production**, so local and test runs never hit the HIBP network API.

## PDF Generation

PDFs are rendered with `spatie/laravel-pdf` (DOMPDF driver) from Blade views in `resources/views/pdf/`:

- `registration.blade.php` — single registration form (`RegistrationController::pdf`, also exposed publicly via
  `PublicRegistrationFormController::registrationPdf`).
- `tournament-simple.blade.php` / `tournament-detailed.blade.php` — tournament match-record sheets.
  `TournamentMatchRecordController::matchRecordsPdf` picks the view via `TournamentPdfTypeEnum` (`simple`/`detailed`),
  whose `viewName()` maps the case to the Blade file.

Use the `laravel-pdf` skill when touching this code.

## Media & File Uploads

Uses `spatie/laravel-medialibrary` (S3-compatible storage via `league/flysystem-aws-s3-v3`). The `Media` model overrides
the package default; the `InteractsWithMedia` trait (`app/Traits/`) is applied to models that own files. Temporary
uploads flow through `TemporaryUploadController` → `StoreTemporaryUploadAction`, validated by
`app/Rules/TemporaryFileRule` and represented by the readonly `App\Support\TemporaryFile` DTO. Use the
`medialibrary-development` skill here.

Models owning files declare their collection name as a class constant and expose a `MorphOne` accessor for the single
file — e.g. `Athlete::PHOTO_MEDIA_COLLECTION_NAME` + `photoMedia()`, `Tournament::COVER_MEDIA_COLLECTION_NAME` +
`coverMedia()`, both registered as `singleFile()`. Follow that shape for new media-owning models.

## Localization

`SetLocale` (prepended to the `api` middleware group) resolves the locale from the `Accept-Language` header against a
hardcoded `['en', 'it']` allowlist, falling back to `config('app.locale')`. Translations are flat JSON in
`lang/en.json` / `lang/it.json`. User-facing error strings from observers/guards go through `__('errors.…')` — add the
key to **both** files.

## Auth & Notifications

Password reset is API-driven: `auth/forgot_password` + `auth/reset_password` send `ResetPasswordNotification`
(`app/Notifications/`), rendered from `resources/views/emails/auth/reset-password.blade.php`.

API exceptions are always rendered as JSON for `api/*` (`withExceptions` in `bootstrap/app.php`), and
`trustProxies(at: '*')` is set.

## Scheduled Work

All scheduling lives in `routes/console.php`: `CleanupTemporaryUploadsCommand` (`app/Console/Commands/`, prunes orphaned
temporary uploads) every six hours, `horizon:snapshot` every five minutes, `AnonymizeExpiredAthletesCommand` +
`PruneExpiredSessionsCommand` + `sanctum:prune-expired --hours=24` + `auth:clear-resets` daily, and
`spatie/laravel-backup` (`backup:clean`/`run`/`monitor`) nightly — the backup entries are registered only when the
destination disk is actually configured, so environments without S3 credentials skip them instead of failing.

## Data Retention & GDPR

- **`AnonymizeExpiredAthletesCommand`** (`app:anonymize-expired-athletes`) — dry-run by default (prints a table via
  `Athlete::retentionExpired()`); pass `--apply` to actually scrub matching rows through `AnonymizeAthleteAction`.
  Eligibility (`Athlete::retentionExpired()` scope) requires the athlete to have **at least one completed
  `MatchRecord`** and for their *most recent* one to be more than 5 years old (`RETENTION_YEARS` on the command) — an
  athlete who never fought a completed match is never auto-anonymized, regardless of registration age.
  `AnonymizeAthleteAction::handle()` overwrites name/tax_number/email/phone/team/birth_date/gender, clears the photo
  media collection, and stamps `anonymized_at`; registrations and match records are left in place (tournament
  history/stats survive) and the athlete row itself is never deleted — deletion is separately blocked by
  `AthleteObserver::deleting` once any history exists.
- **`PruneExpiredSessionsCommand`** (`app:prune-expired-sessions`) — calls the configured session handler's `gc()`
  using `session.lifetime`. Runs alongside `sanctum:prune-expired --hours=24` (personal access tokens) and
  `auth:clear-resets` (password reset tokens) to cover the "tokens" and "sessions" parts of retention.
  `SANCTUM_EXPIRATION` defaults to 10080 minutes (7 days) — see `config/sanctum.php`.
  `LOG_DAILY_DAYS` (`.env.example`, default 365) bounds how long the `daily` log channel keeps rotated files.
- **`ExportAthleteDataCommand`** (`app:export-athlete-data {athlete} {--disk=local} {--path=}`) — GDPR data-portability
  export. `ExportAthleteDataAction::handle()` builds CSV sections (profile, match-records history, registrations,
  match records) that the command writes to the given disk as `{path}/{section}.csv` (default path
  `exports/athlete-{id}-{timestamp}`).
- The `gdpr-compliant` skill (`.claude/skills/gdpr-compliant/`) documents the broader data-rights/security posture
  this retention tooling implements — activate it when touching personal-data handling, retention, or export code.

## CI

CI is **Forgejo Actions**, not GitHub Actions: `.forgejo/workflows/docker-publish.yml` (Docker Hub) and
`ghcr-publish.yml` (GHCR). Each runs the same gate before building the image — `composer install`, then
`composer audit --no-dev` (a vulnerable direct dependency fails the build), then `php artisan test --compact`.

## Production Deploy

Production runs a single `matches-api` image (`docker/production/Dockerfile`, two-stage on `php:8.5-fpm-bookworm`)
alongside `postgres:17-alpine` and `redis:7-alpine`. Inside the app container, supervisord
(`docker/production/supervisord.conf`) drives **nginx** (`nginx.conf`), php-fpm (`php-fpm.conf`), Horizon, Reverb and
the scheduler; `entrypoint.sh` runs `artisan optimize` + `migrate --force` on boot. Local dev is Sail and unaffected by
these files.

**There is no `compose.production.yml` and no `.env.production.example` in this repo** — `README.md` (Deployment
section) still claims both, and its `docker compose -f compose.production.yml up -d --build` command will fail. The web
server is nginx, not Caddy; there is no `Caddyfile` anywhere. No `.env` is baked into the image: every setting
(`APP_KEY`, `DB_*`, `REDIS_*`, `LOG_CHANNEL=daily`, `LARAVEL_PDF_DRIVER=dompdf`, `HORIZON_*`/`LOG_VIEWER_*` credentials)
must be injected as an environment variable at deploy time. The orchestration itself is currently undocumented.

TLS is terminated by a reverse proxy in *front* of the container, which maps hostnames onto the two published ports:
`api.<domain>` → `:80` (API, `/horizon`, `/log-viewer`) and `reverb.<domain>` → `:8080` (websockets). That is why
`nginx.conf` has no `server_name`. In `supervisord.conf`, `REVERB_SERVER_PORT` is the *listen* port; `REVERB_HOST`/
`REVERB_PORT`/`REVERB_SCHEME` are what clients are told to connect to and must not be reused there.

Worst-case container memory is bounded by two settings that have to be tuned **together** on a small VPS:
`pm.max_children=8` × `memory_limit=256M` (php-fpm, ~2 GB) and `maxProcesses=10` × `memory=128` (`config/horizon.php`
`production` block, ~1.3 GB). Both pools share the container with nginx and Reverb, so an OOM in either kills all five
processes.

## Testing

There is one feature test per controller in `tests/Feature/` (`{Controller}Test.php`), covering every resource plus
auth, dashboard, nested tournament routes, and both public controllers, plus cross-cutting suites that are *not*
controller-shaped: `SecurityHeadersTest`, `HorizonTest`, `LogViewerTest` (basic-auth gates), and the retention/export
commands under `tests/Feature/Commands/` (`AnonymizeExpiredAthletesCommandTest`, `PruneExpiredSessionsCommandTest`,
`ExportAthleteDataCommandTest`). Unit tests are bootstrap-only.

`Tests\TestCase` (`tests/TestCase.php`) provides the shared setup: `LazilyRefreshDatabase`, response cache forced off
(so public-endpoint assertions are deterministic), and `$this->authenticate(?User $user)` which `Sanctum::actingAs()` a
factory user. Admin tests call `authenticate()` first; public tests don't.

Tests build state from factories (`database/factories/`), assert via `getJson`/`postJson` against the full
`/api/admin/...` or `/api/public/...` path, and group cases with `// ---- index ----` style comment banners per
controller action.

Tests run against a **real PostgreSQL database** (`matches_api_test` on the Sail `pgsql` host, per `phpunit.xml`) — not
SQLite — so Postgres-specific SQL (`whereRaw`, `whereLike`, `gen_random_uuid()`) is fair game. Broadcasting, queue,
cache, and mail are all nulled/arrayed in `phpunit.xml`.

## Key Commands

```bash
vendor/bin/sail up -d                                           # start dev stack
vendor/bin/sail artisan test --compact                          # full test suite
vendor/bin/sail artisan test --compact --filter=testName        # single test
vendor/bin/sail artisan test --compact tests/Feature/UserControllerTest.php
vendor/bin/sail bin pint --dirty --format agent                 # format changed PHP files
vendor/bin/sail artisan route:list --path=api --except-vendor   # inspect API routes
vendor/bin/sail artisan migrate:fresh --seed                    # reset + seed the dev DB
```

Laravel Boost is wired as an MCP server (`.mcp.json` → `sail artisan boost:mcp`). Prefer its `search-docs`,
`database-schema`, and `database-query` tools over ad-hoc tinker/SQL.

## Reference Docs

- `README.md` — feature overview, setup, full endpoint table, WebSocket contract.
- `DB.md` — the authoritative DBML ER schema; the input format the `laravel-scaffold` skill consumes.
- `DB_SEED.md` — seeded reference data (disciplines, weight categories, experience tiers).
- `docs/vps_costs/README.md` — production resource footprint derived from `docker/production/`, VPS sizing and OVHcloud
  cost estimate; also documents the `BACKUP_DISK`/`MEDIA_DISK` Object Storage wiring.
- `TODO.md` (Italian) — outstanding GDPR gaps tracked against the frontend's `matches-dashboard/TODO.md` (not in this
  repo); legal/retention decisions belong to the Titolare, not this codebase.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

</laravel-boost-guidelines>
