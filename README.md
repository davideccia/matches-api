# Matches API

A REST API backend for managing combat sports tournaments — athletes, registrations, brackets, and live match tracking.

Built with **Laravel 13**, **Sanctum** authentication, and **Laravel Reverb** for real-time match broadcasting.

---

## Features

- **Tournament management** — create and manage tournaments through their full lifecycle (`SCHEDULED → REGISTRATIONS_OPENED → IN_PROGRESS → COMPLETED`)
- **Athlete registry** — athlete profiles with gender, weight category, discipline defaults, and team affiliation
- **Registrations** — link athletes to tournaments with per-registration discipline, weight category, arrival, and weigh-in tracking
- **Match records** — full fight card management: corners, scores, judges points (per-round, per-judge), end method, and winner
- **Real-time broadcasting** — match record changes broadcast over WebSocket via Laravel Reverb on the `tournaments/{id}/match_records` channel
- **Sanctum token auth** — token-based API authentication with per-client token abilities

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Auth | Laravel Sanctum 4 |
| WebSockets | Laravel Reverb |
| Database | SQLite (local dev) |
| Dev environment | Laravel Sail (Docker) |
| Testing | PHPUnit 12 |

---

## Getting Started

### Prerequisites

- Docker Desktop
- [Laravel Sail](https://laravel.com/docs/sail) (included via Composer)

### Setup

```bash
# Install dependencies and scaffold the environment
composer install
cp .env.example .env
php artisan key:generate

# Start the dev stack
vendor/bin/sail up -d

# Run migrations and seed dev data
vendor/bin/sail artisan migrate --seed
```

The API is available at `http://localhost/api/v1/`.

### Default credentials

After seeding, a superadmin account is available:

```
email:    superadmin@matches.it
password: 12345678
```

---

## API Reference

All routes require `Authorization: Bearer <token>` except the login endpoint.

### Authentication

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/v1/auth/login` | Obtain a Sanctum token |
| `GET` | `/api/v1/auth/user` | Get the authenticated user |
| `POST` | `/api/v1/auth/logout` | Revoke the current token |

### Resources

| Resource | Base path | Notes |
|----------|-----------|-------|
| Users | `/api/v1/users` | Full CRUD |
| Athletes | `/api/v1/athletes` | Full CRUD, searchable |
| Disciplines | `/api/v1/disciplines` | Full CRUD |
| Weight Categories | `/api/v1/weight_categories` | Full CRUD |
| Tournaments | `/api/v1/tournaments` | Full CRUD |
| Registrations | `/api/v1/registrations` | Full CRUD |
| Match Records | `/api/v1/match_records` | Full CRUD |
| Tournament Registrations | `/api/v1/tournaments/{id}/registrations` | `index`, `store` |
| Tournament Matches | `/api/v1/tournaments/{id}/match_records` | `index`, `store` |

---

## Data Model

See [`DB.md`](DB.md) for the full DBML schema. Key entities:

```
Tournament ──< Registration >── Athlete
Tournament ──< MatchRecord ──── red_corner / blue_corner / winner (Athlete)
MatchRecord ──< judges_points (JSON: per-round, per-judge scores)
```

Enum values are stored as strings in the database and cast to PHP-backed enums in models:

- `MatchStatus`: `SCHEDULED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`
- `TournamentStatus`: `SCHEDULED`, `REGISTRATIONS_OPENED`, `REGISTRATIONS_CLOSED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`
- `EndMethod`: `VICTORY_UNANIMOUS_DECISION`, `VICTORY_SPLIT_DECISION`, `VICTORY_KO`, `VICTORY_TKO`, `VICTORY_DISQUALIFICATION`, `DRAW`, `NO_CONTEST`
- `Gender`: `MALE`, `FEMALE`, `HYBRID`

All primary and foreign keys are UUIDs.

---

## Real-time Events

Match record changes fire a `MatchRecordChanged` event that broadcasts over the public channel:

```
tournaments/{tournament_id}/match_records
```

Payload: `{ "refresh": true }`

Clients should subscribe to this channel and refetch match data on receipt.

---

## Development

```bash
# Run tests
vendor/bin/sail artisan test --compact

# Lint and format PHP
vendor/bin/sail bin pint --dirty

# Inspect routes
vendor/bin/sail artisan route:list --path=api --except-vendor

# Tail logs
vendor/bin/sail artisan pail
```

> [!NOTE]
> The dev seed data includes 160 athletes, 3 tournaments, 180 registrations, and 90 match records. See [`DB_SEED.md`](DB_SEED.md) for full details.
