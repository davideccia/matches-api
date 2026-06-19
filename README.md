<div align="center">

<img src="storage/app/public/web-app-manifest-512x512.png" alt="Matches API logo" width="120" />

# Matches API

**A Laravel 13 REST API for managing combat-sports tournaments** — athletes, registrations, fight cards, and live match
tracking.

Built with [Laravel 13](https://laravel.com), [Sanctum](https://laravel.com/docs/sanctum) for token auth,
and [Reverb](https://laravel.com/docs/reverb) for real-time WebSocket broadcasting.

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white)
![Tests](https://img.shields.io/badge/Tests-PHPUnit_12-3776AB)

[Getting started](#getting-started) • [API reference](#api-reference) • [Data model](#data-model) • [Real-time events](#real-time-events) • [Development](#development) • [Docs](#documentation)

</div>

---

A pure JSON API (no frontend) for organisers running boxing, kickboxing, and MMA-style events. It tracks the full
lifecycle: schedule a tournament, register athletes, build the ordered fight card, and push live updates to scoreboards
as bouts change.

> [!NOTE]
> Dashboard [**here**](https://codeberg.org/davideccia/matches-dashboard-laravel)

## Features

- **Tournament lifecycle** — drive tournaments through
  `scheduled → registrations_opened → registrations_closed → in_progress → completed / cancelled`.
- **Athlete registry** — profiles with gender, team, and default discipline/weight category; publicly searchable by tax
  number.
- **Registrations** — link athletes to tournaments per discipline and weight category, tracking payment, arrival, and
  weigh-in.
- **Fight card management** — full match records: red/blue corners, rounds, per-round/per-judge scoring, end method, and
  winner.
- **Automatic card ordering** — match records keep a contiguous `sort` index per tournament; insert/move/delete reorders
  the card transactionally.
- **Real-time broadcasting** — every match-record change broadcasts over a public WebSocket channel, ready for live
  scoreboards.
- **Public registration form API** — unauthenticated endpoints for athlete self-lookup, self-registration, and PDF
  export.
- **PDF generation** — registration confirmations and tournament fight cards (simple or detailed layouts).
- **Relation sideloading** — clients control eager loading with `?with=relation1,relation2` on any index/show endpoint.
- **Bilingual** — `en` / `it` responses selected from the `Accept-Language` header.

## Tech stack

| Layer           | Technology                                    |
|-----------------|-----------------------------------------------|
| Framework       | Laravel 13 (PHP 8.3+)                         |
| Auth            | Laravel Sanctum 4 (API tokens)                |
| WebSockets      | Laravel Reverb                                |
| Queues / jobs   | Laravel Horizon (Redis in production)         |
| Database        | SQLite (local), PostgreSQL-compatible queries |
| Files           | spatie/laravel-medialibrary (+ S3)            |
| PDF             | spatie/laravel-pdf (+ dompdf)                 |
| Dev environment | Laravel Sail (Docker)                         |
| Testing         | PHPUnit 12                                    |

## Getting started

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Composer](https://getcomposer.org/) (to install Sail before the containers exist)

### Setup

```bash
# Install PHP dependencies
composer install

# Create your env file and generate the app key
cp .env.example .env
php artisan key:generate

# Start the Sail dev stack (PHP, database, Redis, Reverb)
vendor/bin/sail up -d

# Build the schema and load development data
vendor/bin/sail artisan migrate --seed
```

The API is then served at **`http://localhost`** under two prefixes:

- `http://localhost/api/admin/*` — authenticated organiser routes
- `http://localhost/api/public/*` — unauthenticated, rate-limited public routes

> [!NOTE]
> The dev seed loads athletes, disciplines, weight categories, tournaments, registrations, and match records across
> various statuses. See [`DB_SEED.md`](DB_SEED.md) for the full reference. In production, only the admin user is seeded.

### Default credentials

```
email:    superadmin@matches.it
password: 12345678
```

Obtain a token:

```bash
curl -X POST http://localhost/api/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@matches.it","password":"12345678"}'
```

## API reference

> [!IMPORTANT]
> Routes are **not versioned**. They live under two prefixes mounted in `bootstrap/app.php`: `/api/admin` (
> Sanctum-authenticated) and `/api/public` (throttled at 10 req/min). All IDs are UUIDs, and all responses are JSON.

### Authentication

Admin routes (except those below) require a bearer token:

```
Authorization: Bearer <token>
```

| Method | Path                              | Description                     |
|--------|-----------------------------------|---------------------------------|
| `POST` | `/api/admin/auth/login`           | Obtain a Sanctum token          |
| `POST` | `/api/admin/auth/forgot_password` | Request a password-reset link   |
| `POST` | `/api/admin/auth/reset_password`  | Reset the password with a token |
| `GET`  | `/api/admin/auth/user`            | Get the authenticated user      |
| `POST` | `/api/admin/auth/logout`          | Revoke the current token        |

### Admin resources

Standard CRUD unless noted.

| Resource                   | Base path                                       | Notes                                                      |
|----------------------------|-------------------------------------------------|------------------------------------------------------------|
| Dashboard                  | `/api/admin/dashboard`                          | Aggregate counts for active tournaments                    |
| Users                      | `/api/admin/users`                              |                                                            |
| Athletes                   | `/api/admin/athletes`                           | Searchable via `?search=`                                  |
| Disciplines                | `/api/admin/disciplines`                        |                                                            |
| Weight categories          | `/api/admin/weight_categories`                  |                                                            |
| Tournaments                | `/api/admin/tournaments`                        |                                                            |
| Registrations              | `/api/admin/registrations`                      | Filters: `?unpaid=`, `?unarrived=`, `?weight_in_exceeded=` |
| Match records              | `/api/admin/match_records`                      |                                                            |
| Tournament → registrations | `/api/admin/tournaments/{id}/registrations`     | `index`, `store`                                           |
| Tournament → match records | `/api/admin/tournaments/{id}/match_records`     | `index`, `store`                                           |
| Registration PDF           | `/api/admin/registrations/{id}/pdf`             | Download                                                   |
| Fight card PDF             | `/api/admin/tournaments/{id}/match_records/pdf` | `?type=simple\|detailed`                                   |

### Public endpoints

No authentication required (rate-limited).

| Method | Path                                                   | Description                            |
|--------|--------------------------------------------------------|----------------------------------------|
| `GET`  | `/api/public/registration_form/athletes/{tax_number}`  | Look up an athlete by tax number       |
| `POST` | `/api/public/registration_form/athletes`               | Create/update an athlete               |
| `GET`  | `/api/public/registration_form/tournaments`            | List tournaments open for registration |
| `GET`  | `/api/public/registration_form/disciplines`            | List disciplines                       |
| `GET`  | `/api/public/registration_form/weight_categories`      | List weight categories                 |
| `POST` | `/api/public/registration_form/registrations`          | Submit a registration                  |
| `GET`  | `/api/public/registration_form/registrations/{id}/pdf` | Download registration PDF              |
| `GET`  | `/api/public/tournaments`                              | Public tournament list                 |
| `GET`  | `/api/public/tournaments/{id}/match_records`           | Live fight card for a tournament       |

### Relation sideloading

Index and show endpoints accept a `?with=` parameter to eager-load related resources and avoid extra round trips.
Available relations are allowlisted per endpoint.

```
GET /api/admin/match_records?with=redCorner,blueCorner,weightCategory
```

### Pagination

Index endpoints support optional pagination. Without `paginate=true`, all matching records are returned.

```
GET /api/admin/athletes?paginate=true&per_page=25&page=2
```

## Data model

See [`DB.md`](DB.md) for the full DBML schema.

```
Tournament ──< Registration >── Athlete
Tournament ──< MatchRecord ──── red_corner / blue_corner / winner → Athlete
Discipline, WeightCategory ──< Registration, MatchRecord  (shared reference data)
```

`MatchRecord.judges_points` is a JSON array of per-round, per-judge scores.

**Enums** (stored as strings, cast to PHP-backed enums in models):

| Enum               | Values                                                                                                                                |
|--------------------|---------------------------------------------------------------------------------------------------------------------------------------|
| `TournamentStatus` | `scheduled`, `registrations_opened`, `registrations_closed`, `in_progress`, `completed`, `cancelled`                                  |
| `MatchStatus`      | `scheduled`, `in_progress`, `completed`, `cancelled`                                                                                  |
| `EndMethod`        | `victory_unanimous_decision`, `victory_split_decision`, `victory_ko`, `victory_tko`, `victory_disqualification`, `draw`, `no_contest` |
| `Gender`           | `male`, `female`, `hybrid`                                                                                                            |

> [!IMPORTANT]
> Deleting a tournament that still has registrations or match records is blocked at the application level and returns
`409 Conflict`.

## Real-time events

Every create, update, and delete on a `MatchRecord` fires a `MatchRecordChanged` event that broadcasts over a public
channel **after the database transaction commits**:

```
tournaments.{tournament_id}.match_records
```

The payload is intentionally minimal — a signal to refetch, not the changed state:

```json
{
    "refresh": true
}
```

Subscribe with a Pusher-compatible client such
as [Laravel Echo](https://laravel.com/docs/reverb#client-side-installation) and refetch the fight card on receipt:

```js
Echo.channel(`tournaments.${tournamentId}.match_records`)
    .listen('MatchRecordChanged', () => {
        fetchMatchRecords();
    });
```

> [!NOTE]
> Broadcasting is queued — to see live events locally, run a queue worker and the Reverb server (see below), and set
`BROADCAST_CONNECTION=reverb`.

## Development

```bash
# Run the full test suite
vendor/bin/sail artisan test --compact

# Run a single test by name
vendor/bin/sail artisan test --compact --filter=testName

# Lint and auto-format changed PHP files
vendor/bin/sail bin pint --dirty

# Inspect all API routes
vendor/bin/sail artisan route:list --path=api --except-vendor

# Tail application logs
vendor/bin/sail artisan pail

# Run the queue worker and Reverb WebSocket server together
vendor/bin/sail composer run queue-ws
```

> [!TIP]
> Use `vendor/bin/sail artisan tinker --execute '...'` (single quotes) for quick in-context PHP. Double-quote PHP
> strings inside: `'User::where("superadmin", true)->count();'`

## Documentation

- [`docs/en/`](docs/en/00-index.md) — full technical documentation (English)
- [`docs/it/`](docs/it/00-index.md) — documentazione tecnica completa (Italiano)
- [`DB.md`](DB.md) — DBML schema
- [`DB_SEED.md`](DB_SEED.md) — development seed reference
- [`CLAUDE.md`](CLAUDE.md) — architecture invariants and conventions
