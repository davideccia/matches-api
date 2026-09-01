<div align="center">

<img src="storage/app/public/web-app-manifest-512x512.png" alt="Matches API logo" width="120" />

# Matches API

**A Laravel 13 REST API for managing combat-sports tournaments** — athletes, registrations, fight cards, and live match
tracking.

Built with [Laravel 13](https://laravel.com), [Sanctum](https://laravel.com/docs/sanctum) for token auth,
and [Reverb](https://laravel.com/docs/reverb) for real-time WebSocket broadcasting.

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.5+-777BB4?logo=php&logoColor=white)
![Tests](https://img.shields.io/badge/Tests-PHPUnit_12-3776AB)

[Getting started](#getting-started) • [API reference](#api-reference) • [Data model](#data-model) • [Matchmaking](#matchmaking) • [Real-time events](#real-time-events) • [Development](#development) • [Docs](#documentation)

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
- **Athlete registry** — profiles with gender, team, birth date, and a running fight count; publicly searchable by tax
  number.
- **Registrations** — link athletes to tournaments per discipline and weight category, tracking payment, arrival, and
  weigh-in.
- **Fight card management** — full match records: red/blue corners, rounds, per-round/per-judge scoring, end method, and
  winner.
- **Automatic card ordering** — match records keep a contiguous `sort` index per tournament; insert/move/delete reorders
  the card transactionally.
- **Matchmaking** — generate a whole fight card from a tournament's registrations, pairing by discipline, weight
  category, gender, age bracket, and experience tier, and surfacing unpairable registrations as `matchmaking_issues`.
- **Experience tiers** — configurable fight-count bands (rookie / intermediate / expert, or whatever you name them) so
  a debutant is never matched against a veteran. Define them globally or override them per tournament.
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
| Framework       | Laravel 13 (PHP 8.5+)                         |
| Auth            | Laravel Sanctum 4 (API tokens)                |
| WebSockets      | Laravel Reverb                                |
| Queues / jobs   | Laravel Horizon on Redis                      |
| Database        | PostgreSQL 17                                 |
| Cache           | Redis                                         |
| Files           | spatie/laravel-medialibrary (+ S3)            |
| Backups         | spatie/laravel-backup (nightly, to S3)        |
| PDF             | spatie/laravel-pdf (+ dompdf)                 |
| Mail            | Mailpit (local), SMTP (production)            |
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

# Start the Sail dev stack (PHP, PostgreSQL, Redis, Reverb, Mailpit)
vendor/bin/sail up -d

# Build the schema and load development data
vendor/bin/sail artisan migrate --seed
```

The API is then served at **`http://localhost`** under two prefixes:

- `http://localhost/api/admin/*` — authenticated organiser routes
- `http://localhost/api/public/*` — unauthenticated, rate-limited public routes

> [!NOTE]
> The dev seed loads athletes, disciplines, weight categories, experience tiers, tournaments, registrations, and match
> records across various statuses. See [`DB_SEED.md`](DB_SEED.md) for the full reference. In production, only the admin user is seeded.

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

| Resource                     | Base path                                            | Notes                                                                                                |
|------------------------------|------------------------------------------------------|------------------------------------------------------------------------------------------------------|
| Dashboard                    | `/api/admin/dashboard`                               | Registration and match counts, per active tournament                                                 |
| Users                        | `/api/admin/users`                                   | Superadmin-only writes                                                                               |
| Athletes                     | `/api/admin/athletes`                                | Filters: `?search=`, `?tournament_id=`, `?gender=`, `?is_adult=`, `?min_match_records_count=`, `?max_…` |
| Disciplines                  | `/api/admin/disciplines`                             |                                                                                                      |
| Weight categories            | `/api/admin/weight_categories`                       |                                                                                                      |
| Experience tiers             | `/api/admin/experience_tiers`                        | Filters: `?tournament_id=`, `?only_global=`, `?enabled=`                                             |
| Tournaments                  | `/api/admin/tournaments`                             |                                                                                                      |
| Registrations                | `/api/admin/registrations`                           | Filters: `?unpaid=`, `?unarrived=`, `?weight_in_exceeded=`                                           |
| Match records                | `/api/admin/match_records`                           |                                                                                                      |
| Tournament → registrations   | `/api/admin/tournaments/{id}/registrations`          | `index`, `store`                                                                                     |
| Tournament → match records   | `/api/admin/tournaments/{id}/match_records`          | `index`, `store`                                                                                     |
| Tournament → experience tiers | `/api/admin/tournaments/{id}/experience_tiers`      | `index`, `store`                                                                                     |
| Tournament → disciplines     | `/api/admin/tournaments/{id}/disciplines`            | `index` — the tournament's disciplines (write them via `disciplines` on the tournament itself)        |
| Generate fight card          | `/api/admin/tournaments/{id}/match_records/generate` | `POST` — run matchmaking over the registrations                                                      |
| Registration PDF             | `/api/admin/registrations/{id}/pdf`                  | Download                                                                                             |
| Fight card PDF               | `/api/admin/tournaments/{id}/match_records/pdf`      | `?type=simple\|detailed` (required)                                                                  |
| Temporary uploads            | `/api/admin/temporary_uploads`                       | `POST` — stage a file for later attachment                                                           |

Each CRUD resource (users, athletes, disciplines, weight categories, experience tiers, tournaments, registrations, match
records) also exposes a bulk delete:

```
DELETE /api/admin/{resource}/bulk    {"ids": ["<uuid>", "<uuid>"]}
```

### Public endpoints

No authentication required (rate-limited).

| Method | Path                                                   | Description                            |
|--------|--------------------------------------------------------|----------------------------------------|
| `GET`  | `/api/public/registration_form/athletes/{tax_number}`  | Look up an athlete by tax number       |
| `POST` | `/api/public/registration_form/athletes`               | Create/update an athlete               |
| `GET`  | `/api/public/registration_form/tournaments`            | List tournaments open for registration |
| `GET`  | `/api/public/registration_form/tournaments/{id}/disciplines` | List a tournament's disciplines  |
| `GET`  | `/api/public/registration_form/weight_categories`      | List weight categories                 |
| `POST` | `/api/public/registration_form/registrations`          | Submit a registration                  |
| `GET`  | `/api/public/registration_form/registrations/{id}/pdf` | Download registration PDF              |
| `GET`  | `/api/public/tournaments`                              | Public tournament list                 |
| `GET`  | `/api/public/tournaments/{id}/match_records`           | Live fight card for a tournament       |

### Operational dashboards

Two web UIs ship with the API, both served from the same host and both behind HTTP basic auth
(`HorizonBasicAuth` / `LogViewerBasicAuth` — set their credentials via env, they are not part of Sanctum):

| Path          | What it is                                                        |
|---------------|-------------------------------------------------------------------|
| `/horizon`    | Queue dashboard — job throughput, failures, retries               |
| `/log-viewer` | Application log browser, reading the `daily` channel from `storage/logs` |

> [!NOTE]
> `/log-viewer` needs `LOG_CHANNEL=daily` to have files to read. In production `storage/logs` should be a persistent
> volume, otherwise the log history dies with the container.

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
Tournament ──< ExperienceTier   (tournament_id nullable — null rows are global defaults)
Discipline, WeightCategory ──< Registration, MatchRecord  (shared reference data)
```

`MatchRecord.judges_points` is a JSON array of per-round, per-judge scores.

An athlete's experience is `generic_match_records_count` (bouts fought before joining this system, entered by hand) plus
`registered_match_records_count` (completed bouts tracked here, kept in sync automatically). The sum is exposed as the
computed `match_records_count` field and is what experience tiers are matched against.

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

## Matchmaking

`POST /api/admin/tournaments/{id}/match_records/generate` builds a fight card from the tournament's registrations.
Athletes already paired in that tournament are skipped; everyone else is grouped, and each group is paired off two at a
time. Two athletes are matched only when **all five** of these agree:

```
discipline · weight category · gender · adult or minor · experience tier
```

### Half bouts

Both `red_corner_id` and `blue_corner_id` are nullable. An athlete left over in an otherwise valid group still gets a
match record with the **red corner only** — a *half bout* — so they are on the fight card while waiting for an
opponent, and they stay listed in `matchmaking_issues` all the same. Every match record exposes a computed
`unpaired` boolean, true whenever either corner is still empty.

Re-running the generation completes existing half bouts instead of duplicating them: a compatible athlete registering
later is dropped into the empty corner. A bout entered by hand with the blue corner alone is completed the same way,
on its red corner. Athletes matching **no** tier (`no_tier`) get no match record at all.

### Experience tiers

A tier is a named `min_match_count … max_match_count` band (leave the max empty for an open-ended top tier). Only
`enabled` tiers count, and overlapping enabled ranges are rejected with `400`.

Tiers with a null `tournament_id` are the **global defaults**. If a tournament defines any enabled tiers of its own,
they **replace** the globals for that tournament entirely — they are not merged.

```bash
# A global "rookie" tier: 0-2 fights
curl -X POST http://localhost/api/admin/experience_tiers \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"label":"rookie","min_match_count":0,"max_match_count":2,"enabled":true}'
```

### Matchmaking issues

Registrations that could not be paired are stored on the tournament as `matchmaking_issues`, each with a reason:

| Reason     | Meaning                                                       |
|------------|---------------------------------------------------------------|
| `no_tier`  | The athlete's fight count falls outside every enabled tier     |
| `unpaired` | The athlete was the odd one out in an otherwise valid group (they hold a half bout) |

The list is kept current automatically as match records are created, edited, or deleted.

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
> Broadcasting is queued. `BROADCAST_CONNECTION=reverb` is already the default — to see live events locally you just
> need a queue worker and the Reverb server running (`composer run queue-ws`, see below).

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

# Start Sail, the queue worker, and the Reverb WebSocket server together
make dev
```

> [!TIP]
> Use `vendor/bin/sail artisan tinker --execute '...'` (single quotes) for quick in-context PHP. Double-quote PHP
> strings inside: `'User::where("superadmin", true)->count();'`

## Deployment

Production is a **single application image** (`docker/production/Dockerfile`, a two-stage build on
`php:8.5-fpm-bookworm`) running alongside PostgreSQL and Redis. Inside the container, supervisord
(`docker/production/supervisord.conf`) keeps five processes alive:

```
nginx · php-fpm · Horizon · Reverb · scheduler
```

`entrypoint.sh` recreates the `storage/` tree, fixes ownership, runs `artisan optimize`, and applies migrations before
handing off to supervisord.

```bash
DOCKER_BUILDKIT=1 docker build -f docker/production/Dockerfile -t matches-api .
```

> [!IMPORTANT]
> **This repo does not ship a `compose.production.yml` or a `.env.production.example`.** Orchestration and secret
> delivery are up to your deploy target. No `.env` is baked into the image: every setting (`APP_KEY`, `DB_*`, `REDIS_*`,
> `LOG_CHANNEL=daily`, `LARAVEL_PDF_DRIVER=dompdf`, `HORIZON_*` / `LOG_VIEWER_*` credentials) must be injected as an
> environment variable at runtime.

### TLS and hostnames

TLS is terminated by a reverse proxy **in front of** the container, which maps hostnames onto the two published ports.
This is why `docker/production/nginx.conf` has no `server_name`:

| Hostname          | Port    | Serves                                |
|-------------------|---------|---------------------------------------|
| `api.<domain>`    | `:80`   | The API, plus `/horizon`, `/log-viewer` |
| `reverb.<domain>` | `:8080` | Reverb WebSockets                     |

> [!WARNING]
> In `supervisord.conf`, `REVERB_SERVER_PORT` is the port Reverb **listens** on. `REVERB_HOST` / `REVERB_PORT` /
> `REVERB_SCHEME` are what clients are told to connect to (`reverb.<domain>:443` over `https`) — do not reuse them for
> the listener.

### Sizing

Worst-case container memory is bounded by two settings that must be tuned **together**, because both pools share the
container with nginx and Reverb — an OOM in either takes down all five processes:

| Setting                                              | Worst case |
|------------------------------------------------------|------------|
| `pm.max_children=8` × `memory_limit=256M` (php-fpm)  | ~2 GB      |
| `maxProcesses=10` × `memory=128` (Horizon, prod)     | ~1.3 GB    |

See [`docs/vps_costs/README.md`](docs/vps_costs/README.md) for the measured footprint, VPS sizing, and a worked cost
estimate.

> [!TIP]
> Build in CI, not on the production host — generating the companion dashboard needs 4 GB of Node heap. Set
> `RUN_MIGRATIONS=false` to skip automatic migrations when you would rather run them as a separate deploy step.

### Storage and backups

Both storage concerns are S3-compatible disks, and neither has a safe default:

- **Backups** (`BACKUP_DISK`, default `s3`) — `spatie/laravel-backup` dumps the database nightly at 01:30. The schedule
  in `routes/console.php` is **skipped entirely** when the destination bucket is unconfigured, so a missing `AWS_BUCKET`
  means silently no backups.
- **Media** (`MEDIA_DISK`, falling back to `FILESYSTEM_DISK`, then to the **local** `public` disk) — athlete photos and
  tournament covers. `config/backup.php` backs up the database only (`source.files.include` is empty), so media left on
  local disk is **not** in any backup.

## Documentation

- [`DB.md`](DB.md) — DBML schema
- [`DB_SEED.md`](DB_SEED.md) — development seed reference
- [`CLAUDE.md`](CLAUDE.md) — architecture invariants and conventions
- [`docs/vps_costs/README.md`](docs/vps_costs/README.md) — production resource footprint, VPS sizing, hosting cost estimate
