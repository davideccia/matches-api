# 08 — Real-time, Files & PDF

> See also: [07 — Persistence & Model Lifecycle](07-persistence-and-lifecycle.md), [09 — Configuration & Environment](09-configuration-env.md), [11 — Dependencies](11-dependencies.md)

This chapter covers the three "side-channel" capabilities beyond plain JSON CRUD: real-time
broadcasting over WebSockets, file attachments via Media Library, and server-rendered PDFs.

## Real-time broadcasting (Reverb + Echo)

When the fight card changes, any connected scoreboard should refresh immediately. The app achieves this
by **broadcasting an event over WebSockets** using **Laravel Reverb** (the first-party WebSocket
server) on the backend and **Laravel Echo** (`resources/js/echo.js`) on the client.

### The event

`MatchRecordChanged` implements two interfaces that control *how* and *when* it broadcasts:

```php
// app/Events/MatchRecordChanged.php, lines 13-36 (excerpt)
class MatchRecordChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public readonly MatchRecord $matchRecord) {}

    public function broadcastOn(): array
    {
        return [new Channel("tournaments.{$this->matchRecord->tournament_id}.match_records")];
    }

    public function broadcastWith(): array { return ['refresh' => true]; }
    public function broadcastAs(): string  { return 'MatchRecordChanged'; }
}
```

Three design choices worth understanding:

- **`ShouldBroadcast`** — marks the event for WebSocket delivery; it is pushed through the queue
  (which is why a queue worker must run, see [Chapter 03](03-build-run-test.md)).
- **`ShouldDispatchAfterCommit`** — the broadcast only fires *after* the database transaction commits,
  so clients never get told to refresh against data that was rolled back.
- **Per-tournament public channel** — the channel name embeds the tournament id
  (`tournaments.{id}.match_records`), so each event reaches only the scoreboards watching that
  tournament. It is a plain public `Channel` (no auth), and `channels.php` is empty because no private
  channels are authorised.

### The payload is intentionally minimal

`broadcastWith()` sends just `{"refresh": true}` — not the changed record. This is a deliberate
"signal, not state" pattern: the client receives a nudge and re-fetches the fight card through the
normal JSON endpoint. It keeps the socket payload tiny and avoids duplicating serialization logic.

### Who fires it

The `MatchRecordObserver` (see [Chapter 07](07-persistence-and-lifecycle.md)) dispatches the event on
every create/update/delete via `event(new MatchRecordChanged($matchRecord))`. No controller code is
involved — broadcasting is a side effect of persistence.

## File attachments (Media Library)

Tournaments can carry a **cover image**, handled by `spatie/laravel-medialibrary`, which attaches
uploaded files to any model. The `Media` model and a `media` table store the file metadata; the bytes
live on a filesystem disk (local or S3 via `league/flysystem-aws-s3-v3`).

`Tournament` declares a single-file collection and a convenience relationship:

```php
// app/Models/Tournament.php, lines 25, 44-62 (excerpt)
public const string COVER_MEDIA_COLLECTION_NAME = 'tournaments:cover';

public function registerMediaCollections(): void
{
    $this->addMediaCollection(self::COVER_MEDIA_COLLECTION_NAME)->singleFile();
}

public function coverMedia(): MorphOne
{
    return $this->media()->where('collection_name', self::COVER_MEDIA_COLLECTION_NAME)->one();
}
```

### Two-step upload via temporary files

Uploads are **chunked/deferred**: a client first POSTs the file to `temporary_uploads`
(`TemporaryUploadController` + `StoreTemporaryUploadAction`), receiving a temporary-file handle. On the
subsequent create/update, the controller materialises it onto the model:

```php
// app/Http/Controllers/Api/TournamentController.php, lines 46-48
if (isset($validated['cover'])) {
    $tournament->addMediaFromTemporaryFile($validated['cover'], Tournament::COVER_MEDIA_COLLECTION_NAME);
}
```

A scheduled `CleanupTemporaryUploadsCommand` (every six hours) sweeps temporary files that were never
attached. The `InteractsWithMedia` trait and `TemporaryFileRule`/`TemporaryFile` support classes wire
this together. Download is served through `MediaController`.

## PDF generation

The app server-renders PDFs from **Blade views** using `spatie/laravel-pdf` (backed by `dompdf`).
There are two PDF features, both producing a `PdfBuilder` straight from the controller — Laravel turns
that into a downloadable response.

### Registration PDF

A single athlete's registration confirmation, available on **both** surfaces (admin
`RegistrationController::pdf` and public `PublicRegistrationFormController::registrationPdf`):

```php
// app/Http/Controllers/Api/PublicRegistrationFormController.php, lines 63-72
$registration->loadMissing(['athlete', 'tournament', 'discipline', 'weightCategory']);

return pdf()
    ->view('pdf.registration', compact('registration'))
    ->format(Format::A4)
    ->name("registration-{$registration->id}.pdf");
```

### Tournament fight-card PDF

The whole card, in two layouts chosen by `TournamentPdfTypeEnum` (`simple` / `detailed`), each mapping
to a Blade view (`pdf.tournament-simple` / `pdf.tournament-detailed`):

```php
// app/Http/Controllers/Api/TournamentMatchRecordController.php, lines 62-67
$type = TournamentPdfTypeEnum::from($validated['type']);

return pdf()
    ->view($type->viewName(), ['tournament' => $tournament])
    ->format(Format::A4)
    ->name("tournament-{$tournament->id}-{$type->value}.pdf");
```

The view templates live in `resources/views/pdf/`. The relations are eager-loaded first so the Blade
template can render corners, disciplines, categories, and winners without N+1 queries. PDF driver
configuration is in `config/laravel-pdf.php`.
