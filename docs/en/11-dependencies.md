# 11 — Dependencies

> See also: [08 — Real-time, Files & PDF](08-realtime-files-and-pdf.md), [09 — Configuration & Environment](09-configuration-env.md), [12 — Glossary](12-glossary.md)

This chapter lists the project's third-party packages (from `composer.json`) and the concrete role each
plays. Dependencies are managed by **Composer** (≈ npm/pip for PHP); `composer.json` is the manifest and
`composer.lock` pins exact versions.

## Runtime dependencies (`require`)

| Package | Version | Role in this project |
|---------|---------|----------------------|
| `php` | `^8.3` | Language runtime. Uses modern features: backed enums, constructor property promotion, attributes, `#[\SensitiveParameter]`. |
| `laravel/framework` | `^13.8` | The framework itself — routing, Eloquent ORM, validation, queues, scheduler, broadcasting. |
| `laravel/sanctum` | `^4.0` | API token authentication for the `/api/admin` surface. Provides `HasApiTokens`, `auth:sanctum`, token abilities. |
| `laravel/reverb` | `^1.0` | First-party WebSocket server that delivers `MatchRecordChanged` broadcasts. See [Chapter 08](08-realtime-files-and-pdf.md). |
| `laravel/horizon` | `^5.47` | Dashboard + supervisor for Redis queues in production; runs broadcast/job workers. Snapshotted every 5 min by the scheduler. |
| `laravel/tinker` | `^3.0` | Interactive REPL (`artisan tinker`) for running PHP in app context. |
| `spatie/laravel-pdf` | `^2.12` | Renders Blade views → PDF (registration confirmations, fight cards). |
| `dompdf/dompdf` | `^3.1` | The HTML→PDF rendering engine `spatie/laravel-pdf` drives. |
| `spatie/laravel-medialibrary` | `^11.23` | Attaches uploaded files (tournament cover image) to Eloquent models; the `Media` model + `media` table. |
| `league/flysystem-aws-s3-v3` | `^3.34` | S3 filesystem adapter so media/files can live on Amazon S3 in production. |
| `spatie/laravel-responsecache` | `^8.4` | Full-HTTP-response caching (available; apply to cacheable read endpoints). |

## Development dependencies (`require-dev`)

| Package | Version | Role |
|---------|---------|------|
| `phpunit/phpunit` | `^12.5` | The test runner (feature + unit tests). |
| `laravel/sail` | `^1.62` | Docker dev environment; the `vendor/bin/sail` command. |
| `laravel/pint` | `^1.29` | Opinionated code formatter (≈ Prettier/Black for PHP). |
| `laravel/pail` | `^1.2.5` | Tails application logs in real time (`artisan pail`). |
| `laravel/boost` | `^2.4` | MCP tooling for AI-assisted development (docs search, DB schema/query, logs). |
| `laravel/pao` | `^1.0.6` | Laravel dev tooling. |
| `fakerphp/faker` | `^1.23` | Generates fake data for factories/seeders in tests. |
| `mockery/mockery` | `^1.6` | Mocking library for unit tests. |
| `nunomaduro/collision` | `^8.6` | Pretty CLI error reporting for tests/console. |

## Frontend tooling

Although there is no real UI, a minimal JS toolchain exists for the WebSocket client and demo page:
**Vite** (bundler) with **Laravel Echo** configured in `resources/js/echo.js` to connect to Reverb. Run
it with `vendor/bin/sail npm run build` / `dev`.

## Convenience composer scripts

Defined in `composer.json`:

- `composer setup` — install → copy env → key:generate → migrate → npm install → npm build.
- `composer queue-ws` — runs `queue:work` and `reverb:start` side by side (via `concurrently`) for
  local real-time development.

## Changing dependencies

Per [`CLAUDE.md`](../../CLAUDE.md), do **not** add or change dependencies without approval, and always
install through Sail: `vendor/bin/sail composer require vendor/package`.
