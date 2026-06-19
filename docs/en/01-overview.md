# 01 — Overview

> See also: [02 — Module Structure](02-module-structure.md), [06 — Domain Models](06-domain-models.md), [12 — Glossary](12-glossary.md)

This chapter explains what the system is for, the domain it models, the technology it is built on,
and the single most important structural fact about its HTTP surface: it exposes **two separate API
areas** with different security rules.

## What the system does

The Matches API is the backend for organising **combat-sports tournaments** (boxing, kickboxing,
MMA-style events). A tournament organiser uses it to:

- create a *tournament* and move it through its lifecycle (scheduled → registrations open → in
  progress → completed);
- maintain a registry of *athletes*, each identified publicly by a *tax number* (≈ a national fiscal
  ID, like an Italian *codice fiscale*);
- record *registrations* — an athlete entering a specific tournament in a specific *discipline* and
  *weight category*, tracking whether they paid, arrived, and made weight;
- build the *fight card* — the ordered list of *match records* (bouts), each pairing a red-corner and
  a blue-corner athlete, with rounds, judges' scores, an end method, and a winner;
- broadcast live changes to the fight card over WebSockets so a scoreboard screen updates in
  real time;
- expose a small **public** area so athletes can look themselves up and self-register without logging in,
  and download a registration PDF.

It is an **API only** — it returns JSON, has no web UI of its own, and is meant to be consumed by a
separate frontend application (a single-page app, whose URL is configured as `app.frontend_url`).

## Technology stack

| Layer | Technology | Glossed |
|-------|-----------|---------|
| Language | PHP 8.3+ | The server-side language. |
| Framework | Laravel 13 | The web framework (≈ what Spring is to Java, or NestJS to Node) — routing, ORM, validation, queues. |
| Authentication | Laravel Sanctum 4 | Issues API tokens (≈ bearer tokens) for the authenticated area. |
| Real-time | Laravel Reverb | A first-party WebSocket server for pushing live events to clients. |
| Database | SQLite (local), PostgreSQL-compatible queries | Local dev is SQLite; queries use `ilike` (a Postgres-style case-insensitive match). |
| Background jobs | Laravel Horizon | A dashboard + supervisor for queued jobs running on Redis. |
| PDF | `spatie/laravel-pdf` (+ `dompdf`) | Renders Blade views to PDF files. |
| File storage | `spatie/laravel-medialibrary` (+ S3) | Attaches uploaded files (e.g. a tournament cover image) to models. |
| Dev environment | Laravel Sail | Docker (≈ containerised) dev stack; all commands run through it. |
| Testing | PHPUnit 12 | The test runner. |

See [Chapter 11](11-dependencies.md) for the role of each package.

## The two API surfaces

This is the structural fact to internalise first. Routing is wired up in
`bootstrap/app.php` (≈ the application's startup/configuration file), which mounts **two** route
files under **two** URL prefixes:

```php
// bootstrap/app.php, lines 17-29 (then: closure)
Route::middleware('api')
    ->prefix('api/admin')
    ->group(base_path('routes/api.admin.php'));

Route::middleware(['api', 'throttle:10,1'])
    ->prefix('api/public')
    ->group(base_path('routes/api.public.php'));
```

| Surface | URL prefix | File | Auth | Purpose |
|---------|-----------|------|------|---------|
| **Admin** | `/api/admin/*` | `routes/api.admin.php` | Sanctum token (most routes) | Full CRUD for organisers: tournaments, athletes, registrations, match records, users. |
| **Public** | `/api/public/*` | `routes/api.public.php` | None (rate-limited) | Athlete self-service: look up by tax number, self-register, list open tournaments, download a registration PDF. |

> **Note — the routes are *not* versioned.** Despite the `CLAUDE.md` mention of `/api/v1/`, there is
> no version segment in the URLs. The real prefixes are `/api/admin` and `/api/public`. The root
> `routes/api.php` is an empty placeholder; the two files above are mounted explicitly in the
> `then:` closure of `withRouting()`.

The public surface is globally throttled (`throttle:10,1` ≈ 10 requests per minute per client). The
admin surface relies on Sanctum: in `routes/api.admin.php`, only login and the password-reset
endpoints are open; everything else sits inside a `Route::middleware(['auth:sanctum'])->group(...)`.

## A mental model of a request

Every JSON request flows through the same layered pipeline (detailed in
[Chapter 04](04-request-lifecycle.md)):

```
HTTP request
  → route (api.admin.php / api.public.php)
  → middleware (SetLocale, Sanctum auth, throttle)
  → FormRequest (authorize + validate input)
  → Controller (orchestrate)
  → Model + Observer + Action (business logic, DB)
  → API Resource (shape the JSON)
  → JSON response
```

If you understand that this pipeline is the *same* for nearly every endpoint — only the model and the
specific FormRequest/Resource change — you understand 80% of the codebase. The remaining 20% is the
domain-specific logic in [Chapter 06](06-domain-models.md), [Chapter 07](07-persistence-and-lifecycle.md),
and the patterns in [Chapter 10](10-notable-patterns.md).
