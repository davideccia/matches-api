# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Before Writing Any Code

You MUST invoke the `/laravel-best-practices` skill before analyzing or writing any code. Do not skip this step, even for small changes.

## Project Overview

This is a **Laravel 13 REST API** backend for a combat-sports tournament domain. There is no SPA/frontend; the only Blade views are server-rendered PDF templates (`resources/views/pdf/`) and email templates (`resources/views/emails/`). Auth is handled via Laravel Sanctum.

Local development runs via Laravel Sail: **PostgreSQL** for the database, **Redis** for cache (and sessions/queues), and **Mailpit** for outgoing email. Queues run on Laravel Horizon; real-time broadcasting uses Laravel Reverb.

## Domain Model

Combat sports tournament management. Core entities and their relationships:

- **Tournament** → has many Registrations, MatchRecords; status flows through `TournamentStatusEnum`
- **Athlete** → has many Registrations, MatchRecords (as red/blue corner or winner); looked up publicly by `tax_number`; gender via `AthleteGenderEnum`
- **Registration** — join of Athlete + Tournament + Discipline + WeightCategory; tracks `paid_at`, `arrived`, `weight_in`
- **MatchRecord** — a bout between two athletes; has `sort` (ordered within tournament), `status` (`MatchRecordStatusEnum`), `end_method` (`MatchRecordEndMethodEnum`), `judges_points` (JSON array)
- **Discipline** and **WeightCategory** are reference data shared across tournaments

Enums live in `app/Enums/` and are suffixed `...Enum` (e.g. `TournamentPdfTypeEnum`).

## Architecture Invariants

- **All primary and foreign key IDs are UUIDs** — enforced by the `laravel-scaffold` skill and must be reflected in migrations and models.
- **PHP Enum columns are stored as strings in the database** — cast to a PHP-backed Enum in the model. Never use a DB-level ENUM type.
- **Routes are split by audience, not version** — `bootstrap/app.php` mounts `routes/api.admin.php` under the `/api/admin/` prefix and `routes/api.public.php` under `/api/public/` (the latter with a global `throttle:10,1`). A `SetLocale` middleware is prepended to the `api` group. There is no `/api/v1/` prefix.
- **Eloquent API Resources are mandatory** for all API responses. Public (unauthenticated) endpoints use a separate namespace: `app/Http/Resources/Public/Public{Model}Resource.php`.
- **Model Scopes** live in `app/Models/Scopes/` as dedicated scope classes (e.g., `AthleteScope`), applied to the model's `booted()` method — not inline query builder calls.
- Use `.claude/skills/laravel-scaffold/` to generate a full artifact set (migration, model, observer, scopes, resource, controller, form requests, seeder) from a DBML schema.

## Route Files

`routes/api.php` is empty; the prefixes/groups are wired in `bootstrap/app.php` (see above). Routes live in:
- `routes/api.admin.php` (`/api/admin/`) — `auth/*` (login/logout/forgot/reset/user), `dashboard`, all `apiResource`s (users, weight_categories, disciplines, athletes, tournaments, registrations, match_records), nested `tournaments.{registrations,match_records}` (index/store only), `temporary_uploads`, PDF endpoints, and `DELETE {resource}/bulk` routes (preceding each `apiResource` so they aren't shadowed). Everything except the `auth/*` entry points is behind `auth:sanctum`. Auth endpoints are rate-limited at `throttle:5,1`.
- `routes/api.public.php` (`/api/public/`) — unauthenticated. `registration_form/*` (public athlete lookup by tax_number, athlete/registration create, disciplines & weight_categories index, registration PDF) and `tournaments/*` (public tournament list + match records).

**Custom tournament actions** (not part of the standard CRUD resource) live before the `apiResource` declaration:
- `GET tournaments/{tournament}/match_records/pdf` — `TournamentMatchRecordController::matchRecordsPdf`
- `POST tournaments/{tournament}/match_records/generate` — `TournamentMatchRecordController::generateMatchRecords` (triggers `Tournament::runMatchmaking()`)

## Request / Controller Patterns

Every resource has five FormRequests named `{Model}{Action}Request` (e.g., `MatchRecordStoreRequest`), stored in `app/Http/Requests/{Model}/`. There is also a sixth `{Model}BulkDestroyRequest` pattern for bulk deletes (accepts `ids: uuid[]`).

**`InjectWith` trait** (`app/Traits/InjectWith.php`): all index/show/store requests use this trait. Clients pass `?with=relation1,relation2` as a comma-separated string; the trait converts it to a camelCase array that is then validated against an allowlist in `with.*` rules. Controllers call `$model->loadMissing($validated['with'] ?? [])` to eager-load.

**Pagination**: index requests expose `paginate` (bool), `per_page`, `page` params. When `paginate=true`, the controller calls `->paginate()`; otherwise `->get()`.

**Nested route injection**: for nested routes like `tournaments/{tournament}/match_records`, the store request's `prepareForValidation` injects `tournament_id` from the route-bound model.

## Observers

Each model has an observer (`app/Observers/`) wired via `#[ObservedBy]` attribute. Key responsibilities:
- **Delete guards**: `TournamentObserver::deleting` aborts with 409 if related registrations or match_records exist
- **Sort management**: `MatchRecordObserver` delegates creating/updating/deleting to `ReorderMatchRecordsAction` to maintain contiguous `sort` ordering per tournament
- **WebSocket broadcast**: `MatchRecordObserver` fires `MatchRecordChanged` event on every CRUD operation

## Events & Broadcasting

`MatchRecordChanged` (`app/Events/`) implements `ShouldBroadcast` and `ShouldDispatchAfterCommit`. It broadcasts a `{"refresh": true}` payload on the channel `tournaments.{tournament_id}.match_records`. Use this pattern when real-time push is needed for future resources.

## Actions

`app/Actions/` holds stateless classes for complex business logic extracted from observers/controllers:
- `ReorderMatchRecordsAction` — handles insert/update/delete of the `sort` column across a tournament's match records within DB transactions.
- `StoreTemporaryUploadAction` — backs `POST temporary_uploads` (chunked/temporary file uploads later attached to a model's media).

## PDF Generation

PDFs are rendered with `spatie/laravel-pdf` (DOMPDF driver) from Blade views in `resources/views/pdf/`:
- `registration.blade.php` — single registration form (`RegistrationController::pdf`, also exposed publicly via `PublicRegistrationFormController::registrationPdf`).
- `tournament-simple.blade.php` / `tournament-detailed.blade.php` — tournament match-record sheets. `TournamentMatchRecordController::matchRecordsPdf` picks the view via `TournamentPdfTypeEnum` (`simple`/`detailed`), whose `viewName()` maps the case to the Blade file.

Use the `laravel-pdf` skill when touching this code.

## Media & File Uploads

Uses `spatie/laravel-medialibrary` (S3-compatible storage via `league/flysystem-aws-s3-v3`). The `Media` model overrides the package default; the `InteractsWithMedia` trait (`app/Traits/`) is applied to models that own files. Temporary uploads flow through `TemporaryUploadController` → `StoreTemporaryUploadAction`, validated by `app/Rules/TemporaryFileRule`. Use the `medialibrary-development` skill here.

## Auth & Notifications

Password reset is API-driven: `auth/forgot_password` + `auth/reset_password` send `ResetPasswordNotification` (`app/Notifications/`), rendered from `resources/views/emails/auth/reset-password.blade.php`.

## Testing Status

Test coverage is currently bootstrap-only (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`). New domain logic should ship with feature tests using model factories — do not assume existing coverage protects a change.

## Key Commands

```bash
vendor/bin/sail up -d                                           # start dev stack
vendor/bin/sail artisan test --compact                         # full test suite
vendor/bin/sail artisan test --compact --filter=testName       # single test
vendor/bin/sail bin pint --dirty --format agent                # format changed PHP files
vendor/bin/sail artisan route:list --path=api --except-vendor  # inspect API routes
```

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12

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

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

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
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
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

</laravel-boost-guidelines>
