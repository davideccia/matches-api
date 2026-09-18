# Performance Audit — MEDIUM

Date: 2026-09-18

## 5. JSON-column scopes do full table scans

- **File**: `app/Models/Athlete.php:235-243` (`minMatchRecordsCount` / `maxMatchRecordsCount`)
- **Issue**: These run `whereRaw("(match_records_history->>'total')::int ...")` with no expression index — every call is a full table scan of `athletes`, since JSON extraction can't use a btree on the column. Not on the public path today (used in admin filters), but flagged since matchmaking's per-discipline logic could later be pushed to SQL.
- **Fix**: Add a Postgres expression index `CREATE INDEX ... ON athletes (((match_records_history->>'total')::int))` if these scopes see production traffic.

## 6. Synchronous PDF generation with no cache

- **File**: `app/Http/Controllers/Api/PublicRegistrationFormController.php:141-150` (`registrationPdf`)
- **Issue**: DOMPDF renders inline on every request; acceptable at current volume (rate-limited, cached by browsers via the signed link), but there's no cache-busting/etag, so repeat downloads of the same registration's PDF re-render every time.
- **Fix**: Consider `Cache::remember` keyed by `registration_id`+`updated_at` if this endpoint sees meaningful traffic. Low priority now.
