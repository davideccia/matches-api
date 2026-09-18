# Performance Audit — CRITICAL

Date: 2026-09-18

## 1. Missing indexes on `match_records`

**Status: Completed 2026-09-18** — `database/migrations/2026_09_18_111800_add_indexes_to_match_records_table.php` adds `['tournament_id','sort']`, `red_corner_id`, `blue_corner_id` indexes. Migration verified (up/rollback/re-migrate) and full test suite (424 passed) green.

- **File**: `database/migrations/*_create_match_records_table.php:9-15`
- **Issue**: `tournament_id`, `red_corner_id`, `blue_corner_id`, `weight_category_id`, `discipline_id`, `winner_id` have FK constraints only. Postgres does not auto-index FK columns (only the referenced PK).
- **Failure scenario**: Every public hit on `GET /api/public/tournaments/{id}/match_records`, `.../current`, and `MatchmakingService::selectBookedAthletes()` filters `where tournament_id = ?` then sorts by `sort`, causing a full seq scan as the table grows.
- **Fix**: New migration adding:
  - `$table->index(['tournament_id', 'sort'])` (matches the actual query shape)
  - single-column indexes on `red_corner_id` / `blue_corner_id` (used in `AthleteObserver` / history sync and matchmaking subqueries)

## 2. `spatie/laravel-responsecache` installed but unused

- **File**: `composer.json:26` (dependency present, no middleware wired anywhere)
- **Issue**: Public read-only, unauthenticated, high-traffic endpoints (`tournaments/`, `registration_form/tournaments`, `weight_categories`, `disciplines`) hit the DB on every request with no cache layer, despite the dependency existing for exactly this.
- **Fix**: Apply the package's cache middleware to the public read-only GET routes in `routes/api.public.php`, respecting `Tests\TestCase`'s existing "cache forced off in tests" setup. Use the `responsecache-development` skill.
