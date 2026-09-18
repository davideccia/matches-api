# Performance Audit — HIGH

Date: 2026-09-18

## 3. `registrations` composite index doesn't serve `tournament_id`-only lookups

- **File**: `database/migrations/*_create_registrations_table.php:22`
- **Issue**: The only supporting index is `unique(['athlete_id','tournament_id','discipline_id','weight_category_id'])`, leading with `athlete_id`. But `$tournament->registrations()` — used in `MatchmakingService::resolvePlan/resolveIssuesFromMatchRecords` and both `PublicRegistrationFormController` paths — filters by `tournament_id` alone. A composite index leading with a different column doesn't serve that filter efficiently on Postgres.
- **Fix**: Add `$table->index('tournament_id')` (or reorder into a covering index) in a follow-up migration.

## 4. Unbounded public tournament listing

- **File**: `app/Http/Controllers/Api/PublicRegistrationFormController.php:60-73` (`tournamentsIndex`)
- **Issue**: No `paginate`/`per_page` support at all (unlike the admin/public `PublicTournamentController::tournamentsIndex`), so `->get()` always loads every `REGISTRATIONS_OPENED` tournament. Low risk today (few concurrent open tournaments) but no ceiling exists.
- **Fix**: Reuse the same `InjectWith` + pagination request shape already used in `PublicTournamentIndexRequest`.
