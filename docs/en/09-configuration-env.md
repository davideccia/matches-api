# 09 — Configuration & Environment

> See also: [03 — Build, Run & Test](03-build-run-test.md), [08 — Real-time, Files & PDF](08-realtime-files-and-pdf.md)

This chapter explains how the app is configured: the env-var → config-file convention, the driver
choices that make local development simple, and the security knobs (locale, throttling, Horizon auth).

## How configuration works in Laravel

Configuration lives in `config/*.php`, one file per subsystem. Each file reads values from
**environment variables** via `env('KEY', 'default')`, and code reads the resolved value via
`config('file.key')`. The golden rule: **application code calls `config()`, never `env()` directly** —
because config can be cached in production (`config:cache`), after which `env()` returns null.

Inspect any value with:

```bash
vendor/bin/sail artisan config:show database.default
```

The local template is `.env.example`; copy it to `.env` on first setup.

## Driver choices (the "database for everything" default)

The defining local-dev decision: almost every pluggable subsystem defaults to a **database-backed
driver**, so you can boot the whole app with nothing but SQLite — no Redis, no external services.

| Subsystem | Config file | Default driver |
|-----------|-------------|----------------|
| Database | `config/database.php` | `sqlite` |
| Cache | `config/cache.php` | `database` |
| Queue | `config/queue.php` | `database` |
| Session | `config/session.php` | `database` |
| Broadcasting | `config/broadcasting.php` | `null` (off unless configured) |

Broadcasting defaults to `null` (no-op): real-time push is opt-in. To exercise WebSockets locally you
set `BROADCAST_CONNECTION=reverb` and run the Reverb server + a queue worker (see
[Chapter 03](03-build-run-test.md) and [Chapter 08](08-realtime-files-and-pdf.md)). In production the
queue is intended to run on Redis under Horizon.

## Notable application settings

### Frontend URL

The API is consumed by a separate SPA. Its base URL is configured and used when building
password-reset links:

```php
// config/app.php
'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost:3000'),
```

```php
// app/Models/User.php, lines 36-40
public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
{
    $url = config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($this->email);
    $this->notify(new ResetPasswordNotification($url));
}
```

### Locale

Default locale and fallback are `en`; the supported set is `['en', 'it']`. The `SetLocale` middleware
(see [Chapter 04](04-request-lifecycle.md)) overrides the default per-request from the
`Accept-Language` header. Translation strings live in `lang/en.json` and `lang/it.json`; enum
`label()` methods and observer error messages (`__('errors...')`) resolve against them.

```php
// config/app.php
'locale' => env('APP_LOCALE', 'en'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
```

### CORS

Because a separate-origin SPA calls this API, `config/cors.php` controls which origins/headers are
allowed. Configure it to match the deployed frontend origin.

## Security & rate-limiting knobs

- **Sanctum** (`config/sanctum.php`) — the API-token guard for the admin surface. Login issues a token
  (`createToken('api')`); the `auth:sanctum` middleware group protects every admin route except
  login/password-reset.
- **Throttling** — the entire public surface is wrapped in `throttle:10,1` (10 req/min) in
  `bootstrap/app.php`; the admin `auth/user` endpoint adds its own `throttle:10,1`.
- **`ability` middleware alias** — `bootstrap/app.php` aliases `ability` to Sanctum's
  `CheckForAnyAbility`, available for token-ability checks where needed.
- **Horizon dashboard auth** — `HorizonBasicAuth` middleware + `HorizonServiceProvider` gate access to
  the queue dashboard. Horizon tuning (worker counts, balancing) is in `config/horizon.php`.

## Trusting proxies

`bootstrap/app.php` sets `$middleware->trustProxies(at: '*')`, so the app honours `X-Forwarded-*`
headers — necessary behind a load balancer / reverse proxy so generated URLs and client IPs are
correct. Tighten the `at:` list to your proxy range for production hardening.
