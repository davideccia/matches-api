# Matches API

A REST API backend for managing combat sports tournaments — athletes, registrations, fight cards, and live match tracking.

Built with **Laravel 13**, **Laravel Sanctum** for token authentication, and **Laravel Reverb** for real-time WebSocket broadcasting.

---

## Features

- **Tournament lifecycle** — manage tournaments from scheduling through completion (`SCHEDULED → REGISTRATIONS_OPENED → IN_PROGRESS → COMPLETED`)
- **Athlete registry** — athlete profiles with gender, team, weight category, and discipline defaults; lookup by tax number
- **Registrations** — link athletes to tournaments with per-entry discipline, weight category, arrival, and weigh-in tracking
- **Fight card management** — full match record support: red/blue corners, round configuration, judges' points (per-round, per-judge), end method, winner
- **Sort ordering** — match records maintain a contiguous `sort` index per tournament; insert/update/delete automatically reorders the card
- **Real-time broadcasting** — match record changes broadcast via WebSocket over a public channel, ready for live scoreboards
- **Public registration form API** — unauthenticated endpoints for athlete lookup, registration submission, and PDF export
- **Relation sideloading** — clients control eager loading with `?with=relation1,relation2` on any index or show endpoint

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Auth | Laravel Sanctum 4 |
| WebSockets | Laravel Reverb |
| Database | SQLite (local), PostgreSQL-compatible |
| Dev environment | Laravel Sail (Docker) |
| Testing | PHPUnit 12 |

---

## Getting Started

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Composer (to install Sail before the containers are available)

### Setup

```bash
# Install PHP dependencies
composer install

# Copy env file and generate app key
cp .env.example .env
php artisan key:generate

# Start the Sail dev stack
vendor/bin/sail up -d

# Run migrations and seed development data
vendor/bin/sail artisan migrate --seed
```

The API is available at `http://localhost/api/v1/`.

> [!NOTE]
> The dev seed loads 160 athletes, 3 tournaments, 180 registrations, and 90 match records across various statuses. See [`DB_SEED.md`](DB_SEED.md) for the full reference.

### Default credentials

```
email:    superadmin@matches.it
password: 12345678
```

Obtain a token with:

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@matches.it","password":"12345678"}'
```

---

## API Reference

### Authentication

All routes under `/api/v1/` (except `auth/login` and the public endpoints below) require:

```
Authorization: Bearer <token>
```

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/v1/auth/login` | Obtain a Sanctum token |
| `GET` | `/api/v1/auth/user` | Get the authenticated user |
| `POST` | `/api/v1/auth/logout` | Revoke the current token |

### Admin resources

Standard CRUD unless noted. All IDs are UUIDs.

| Resource | Base path | Notes |
|---|---|---|
| Users | `/api/v1/users` | |
| Athletes | `/api/v1/athletes` | Searchable via `?search=` |
| Disciplines | `/api/v1/disciplines` | |
| Weight Categories | `/api/v1/weight_categories` | |
| Tournaments | `/api/v1/tournaments` | |
| Registrations | `/api/v1/registrations` | Filterable: `?unpaid=`, `?unarrived=`, `?weight_in_exceeded=` |
| Match Records | `/api/v1/match_records` | |
| Tournament → Registrations | `/api/v1/tournaments/{id}/registrations` | `index`, `store` |
| Tournament → Matches | `/api/v1/tournaments/{id}/match_records` | `index`, `store` |

### Public endpoints

No authentication required.

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/v1/registration_form/athletes/{tax_number}` | Look up athlete by tax number |
| `POST` | `/api/v1/registration_form/athletes` | Create a new athlete |
| `GET` | `/api/v1/registration_form/tournaments` | List open tournaments |
| `GET` | `/api/v1/registration_form/disciplines` | List disciplines |
| `GET` | `/api/v1/registration_form/weight_categories` | List weight categories |
| `POST` | `/api/v1/registration_form/registrations` | Submit a registration |
| `GET` | `/api/v1/registration_form/registrations/{id}/pdf` | Download registration PDF |
| `GET` | `/api/v1/tournaments` | Public tournament list |
| `GET` | `/api/v1/tournaments/{id}/match_records` | Live fight card for a tournament |

### Relation sideloading

Index and show endpoints accept a `?with=` query parameter to eager-load related resources, avoiding extra round trips.

```
GET /api/v1/match_records?with=redCorner,blueCorner,weightCategory
```

Available relations are allowlisted per endpoint. Combine multiple relations with a comma.

### Pagination

Index endpoints support optional cursor pagination:

```
GET /api/v1/athletes?paginate=true&per_page=25&page=2
```

Without `paginate=true`, all matching records are returned.

---

## Data Model

See [`DB.md`](DB.md) for the full DBML schema.

```
Tournament ──< Registration >── Athlete
Tournament ──< MatchRecord ──── red_corner / blue_corner / winner → Athlete
MatchRecord.judges_points (JSON): [{round, judge1Red, judge1Blue, judge2Red, judge2Blue, judge3Red, judge3Blue}]
```

**Enums** (stored as strings, cast to PHP-backed enums in models):

| Enum | Values |
|---|---|
| `TournamentStatus` | `scheduled`, `registrations_opened`, `registrations_closed`, `in_progress`, `completed`, `cancelled` |
| `MatchStatus` | `scheduled`, `in_progress`, `completed`, `cancelled` |
| `EndMethod` | `victory_unanimous_decision`, `victory_split_decision`, `victory_ko`, `victory_tko`, `victory_disqualification`, `draw`, `no_contest` |
| `Gender` | `male`, `female`, `hybrid` |

All primary and foreign keys are UUIDs.

> [!IMPORTANT]
> Deleting a tournament with existing registrations or match records is blocked at the application level (returns `409 Conflict`).

---

## Real-time Events

Every create, update, and delete on a `MatchRecord` fires a `MatchRecordChanged` event that broadcasts over a public WebSocket channel after the database transaction commits:

```
tournaments.{tournament_id}.match_records
```

Payload:

```json
{ "refresh": true }
```

Clients should subscribe to this channel using a Pusher-compatible client (e.g. [Laravel Echo](https://laravel.com/docs/reverb#client-side-installation)) and refetch the fight card on receipt.

```js
Echo.channel(`tournaments.${tournamentId}.match_records`)
    .listen('MatchRecordChanged', () => {
        fetchMatchRecords();
    });
```

---

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

# Start queue worker and Reverb WebSocket server together
vendor/bin/sail composer run queue-ws
```

> [!TIP]
> Use `vendor/bin/sail artisan tinker --execute '...'` (single quotes) for quick in-context PHP execution. Double-quote PHP strings inside: `'User::where("superadmin", true)->count();'`
