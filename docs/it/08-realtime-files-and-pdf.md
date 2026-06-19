# 08 — Real-time, File e PDF

> Vedi anche: [07 — Persistenza e Ciclo di Vita del Modello](07-persistence-and-lifecycle.md), [09 — Configurazione e Ambiente](09-configuration-env.md), [11 — Dipendenze](11-dependencies.md)

Questo capitolo copre le tre capacità "laterali" oltre il semplice CRUD JSON: broadcasting real-time via
WebSocket, allegati file tramite Media Library e PDF renderizzati lato server.

## Broadcasting real-time (Reverb + Echo)

Quando la card degli incontri cambia, ogni segnapunti connesso dovrebbe aggiornarsi immediatamente.
L'app lo ottiene **trasmettendo un evento via WebSocket** usando **Laravel Reverb** (il server WebSocket
nativo) sul backend e **Laravel Echo** (`resources/js/echo.js`) sul client.

### L'evento

`MatchRecordChanged` implementa due interfacce che controllano *come* e *quando* trasmette:

```php
// app/Events/MatchRecordChanged.php, righe 13-36 (estratto)
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

Tre scelte di design da capire:

- **`ShouldBroadcast`** — marca l'evento per la consegna via WebSocket; viene spinto attraverso la coda
  (motivo per cui un worker della coda deve girare, vedi [Capitolo 03](03-build-run-test.md)).
- **`ShouldDispatchAfterCommit`** — il broadcast parte solo *dopo* il commit della transazione del
  database, così ai client non viene mai detto di aggiornarsi su dati che sono stati annullati.
- **Canale pubblico per torneo** — il nome del canale incorpora l'id del torneo
  (`tournaments.{id}.match_records`), così ogni evento raggiunge solo i segnapunti che osservano quel
  torneo. È un semplice `Channel` pubblico (senza auth), e `channels.php` è vuoto perché non è
  autorizzato alcun canale privato.

### Il payload è intenzionalmente minimo

`broadcastWith()` invia solo `{"refresh": true}` — non il record modificato. È un pattern deliberato
"segnale, non stato": il client riceve una spinta e ri-recupera la card attraverso il normale endpoint
JSON. Mantiene il payload del socket minuscolo ed evita di duplicare la logica di serializzazione.

### Chi lo lancia

Il `MatchRecordObserver` (vedi [Capitolo 07](07-persistence-and-lifecycle.md)) dispaccia l'evento a ogni
create/update/delete via `event(new MatchRecordChanged($matchRecord))`. Nessun codice del controller è
coinvolto — il broadcasting è un effetto collaterale della persistenza.

## Allegati file (Media Library)

I tornei possono portare una **immagine di copertina**, gestita da `spatie/laravel-medialibrary`, che
allega file caricati a qualsiasi modello. Il modello `Media` e una tabella `media` memorizzano i
metadati del file; i byte vivono su un disco filesystem (locale o S3 via `league/flysystem-aws-s3-v3`).

`Tournament` dichiara una collection a file singolo e una relazione di comodità:

```php
// app/Models/Tournament.php, righe 25, 44-62 (estratto)
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

### Upload in due passi tramite file temporanei

Gli upload sono **a chunk/differiti**: un client prima fa POST del file su `temporary_uploads`
(`TemporaryUploadController` + `StoreTemporaryUploadAction`), ricevendo un handle di file temporaneo. Al
successivo create/update, il controller lo materializza sul modello:

```php
// app/Http/Controllers/Api/TournamentController.php, righe 46-48
if (isset($validated['cover'])) {
    $tournament->addMediaFromTemporaryFile($validated['cover'], Tournament::COVER_MEDIA_COLLECTION_NAME);
}
```

Un `CleanupTemporaryUploadsCommand` schedulato (ogni sei ore) ripulisce i file temporanei mai allegati.
Il trait `InteractsWithMedia` e le classi di supporto `TemporaryFileRule`/`TemporaryFile` collegano il
tutto. Il download è servito tramite `MediaController`.

## Generazione PDF

L'app renderizza lato server i PDF da **view Blade** usando `spatie/laravel-pdf` (basato su `dompdf`).
Ci sono due funzionalità PDF, entrambe che producono un `PdfBuilder` direttamente dal controller —
Laravel lo trasforma in una risposta scaricabile.

### PDF di iscrizione

La conferma di iscrizione di un singolo atleta, disponibile su **entrambe** le superfici (admin
`RegistrationController::pdf` e pubblica `PublicRegistrationFormController::registrationPdf`):

```php
// app/Http/Controllers/Api/PublicRegistrationFormController.php, righe 63-72
$registration->loadMissing(['athlete', 'tournament', 'discipline', 'weightCategory']);

return pdf()
    ->view('pdf.registration', compact('registration'))
    ->format(Format::A4)
    ->name("registration-{$registration->id}.pdf");
```

### PDF della card del torneo

L'intera card, in due layout scelti da `TournamentPdfTypeEnum` (`simple` / `detailed`), ciascuno mappato
a una view Blade (`pdf.tournament-simple` / `pdf.tournament-detailed`):

```php
// app/Http/Controllers/Api/TournamentMatchRecordController.php, righe 62-67
$type = TournamentPdfTypeEnum::from($validated['type']);

return pdf()
    ->view($type->viewName(), ['tournament' => $tournament])
    ->format(Format::A4)
    ->name("tournament-{$tournament->id}-{$type->value}.pdf");
```

I template delle view vivono in `resources/views/pdf/`. Le relazioni sono caricate in eager prima così
che il template Blade possa renderizzare angoli, discipline, categorie e vincitori senza query N+1. La
configurazione del driver PDF è in `config/laravel-pdf.php`.
