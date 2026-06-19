# 03 — Build, Run & Test

> See also: [09 — Configuration & Environment](09-configuration-env.md), [08 — Real-time, Files & PDF](08-realtime-files-and-pdf.md)

This chapter explains how to get the project running locally and how to exercise it. The canonical
command list also lives in [`CLAUDE.md`](../../CLAUDE.md) and the [`README.md`](../../README.md); this
chapter explains *why* each step exists for someone new to Laravel.

## Everything runs through Sail

The project uses **Laravel Sail** — a thin wrapper around `docker compose` that runs PHP, the
database, Redis, and Reverb in containers (≈ isolated lightweight VMs). The practical rule: **prefix
every PHP/Artisan/Composer/Node command with `vendor/bin/sail`**. Running `php` directly on your host
will use the wrong PHP version and miss the services.

`artisan` (≈ the framework's CLI, like `manage.py` in Django or `rails`) is how you run framework
tasks: migrations, route listing, the test suite, scheduled commands.

## First-time setup

```bash
composer install                 # install PHP deps (needed to get Sail itself)
cp .env.example .env             # create local config from the template
vendor/bin/sail up -d            # start containers in the background
vendor/bin/sail artisan key:generate   # generate the app encryption key
vendor/bin/sail artisan migrate  # build the database schema
```

There is also a `composer setup` script (in `composer.json`) that chains install → env → key →
migrate → npm install → npm build for convenience.

Local development defaults (see [Chapter 09](09-configuration-env.md)): the database is **SQLite**, and
**sessions, cache, and queues all use the database driver** — so you do not need Redis just to boot.
Redis/Horizon matter when you exercise background jobs.

## Day-to-day commands

| Goal | Command |
|------|---------|
| Start the stack | `vendor/bin/sail up -d` |
| Stop the stack | `vendor/bin/sail stop` |
| Run a migration | `vendor/bin/sail artisan migrate` |
| List API routes | `vendor/bin/sail artisan route:list --path=api --except-vendor` |
| Inspect a config value | `vendor/bin/sail artisan config:show database.default` |
| Format changed PHP | `vendor/bin/sail bin pint --dirty --format agent` |
| Open a REPL | `vendor/bin/sail artisan tinker` |

`pint` is the code formatter (≈ Prettier/Black for PHP). Run it before finalising any PHP change.

## Real-time + queue + Horizon

Because match-record changes broadcast over WebSockets ([Chapter 08](08-realtime-files-and-pdf.md)),
fully exercising the app means running three things: the HTTP server, a **queue worker** (broadcasts
are queued jobs), and the **Reverb** WebSocket server.

A convenience script bundles two of them (`composer.json` → `scripts.queue-ws`):

```bash
# runs `queue:work` and `reverb:start` side by side
composer queue-ws
```

In production the queue is driven by **Horizon** (`vendor/bin/sail artisan horizon`), which also
exposes a dashboard. The scheduler (`routes/console.php`) keeps Horizon metrics fresh with
`horizon:snapshot` every five minutes and runs the temporary-upload cleanup every six hours; both fire
from Laravel's scheduler (`vendor/bin/sail artisan schedule:work` locally, or a single system cron in
production).

```php
// routes/console.php, lines 6-7
Schedule::command(CleanupTemporaryUploadsCommand::class)->everySixHours()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
```

## Frontend assets

There is a Vite (≈ JS bundler) setup with `resources/js/echo.js` (the WebSocket client config) and
`resources/css/app.css`. These exist to support the broadcasting demo and the `welcome` page, not a
real UI. If you ever hit a *Vite manifest* error, run `vendor/bin/sail npm run build`.

## Testing

The suite uses **PHPUnit 12** (not Pest). Tests live in `tests/Feature/` (full request-level tests,
the default and preferred kind) and `tests/Unit/` (isolated class tests).

```bash
vendor/bin/sail artisan test --compact                       # whole suite
vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php   # one file
vendor/bin/sail artisan test --compact --filter=testName     # one test by name
```

> **Current state:** only the scaffolded `ExampleTest` placeholders exist in `tests/Feature/` and
> `tests/Unit/`. The conventions in `CLAUDE.md` (use factories, cover happy/failure/edge paths, write
> feature tests) describe how new tests *should* be written as the domain logic gains coverage.

Create new tests with `vendor/bin/sail artisan make:test --phpunit {Name}` (add `--unit` for a unit
test).
