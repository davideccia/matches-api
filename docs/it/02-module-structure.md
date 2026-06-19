# 02 — Struttura dei Moduli

> Vedi anche: [04 — Ciclo di Vita della Richiesta](04-request-lifecycle.md), [05 — Controller e Request](05-controllers-and-requests.md), [10 — Pattern Notevoli](10-notable-patterns.md)

Questo capitolo è una mappa dell'albero dei sorgenti. I progetti Laravel seguono una forte convenzione
di directory, quindi una volta che sai cosa *significa* ogni cartella, puoi navigare per intuito. Tutto
il codice specifico dell'applicazione vive sotto `app/`.

## Layout di primo livello

| Percorso | Ruolo |
|----------|-------|
| `app/` | Tutto il codice PHP applicativo (modelli, controller, logica di business). Il cuore del progetto. |
| `routes/` | Mappature URL → controller. Vedi [Capitolo 01](01-overview.md) per le due superfici. |
| `database/migrations/` | Definizioni di schema versionate (≈ script SQL ordinati che costruiscono le tabelle). |
| `config/` | Un file per sottosistema (database, coda, broadcasting, …) che legge dalle variabili d'ambiente. |
| `resources/views/` | Template Blade — qui solo PDF e una email; nessuna UI. |
| `lang/` | Stringhe di traduzione: `en.json`, `it.json`. |
| `tests/` | Test PHPUnit. |
| `bootstrap/app.php` | Avvio dell'applicazione: routing, middleware, rendering delle eccezioni. |
| `.claude/skills/` | Skill di automazione di progetto (es. `laravel-scaffold`) — tooling, non codice runtime. |

## Dentro `app/`

La directory `app/` è organizzata per **layer** (≈ responsabilità), non per feature. Una singola
feature come "match record" è distribuita su molte cartelle — un modello qui, un controller là, le
request altrove. Questo è idiomatico per Laravel.

```
app/
├── Actions/          classi di logica di business stateless
├── Console/Commands/ comandi CLI artisan (task schedulati)
├── Enums/            enum PHP per le colonne stato/tipo
├── Events/           eventi trasmissibili (broadcast)
├── Http/
│   ├── Controllers/  gestori delle richieste
│   ├── Middleware/    filtri trasversali sulle richieste
│   ├── Requests/      validazione + autorizzazione per azione
│   └── Resources/     modellazione dell'output JSON
├── Models/           entità Eloquent
│   └── Scopes/       global scope di query (uno per modello)
├── Notifications/    es. email di reset password
├── Observers/        hook del ciclo di vita del modello (uno per modello)
├── Providers/        registrazione/bootstrap dei servizi
├── Rules/            regole di validazione custom
├── Support/          semplici value object di supporto
└── Traits/           mixin riutilizzabili (es. InjectWith)
```

### Layer per layer

**`Models/`** — modelli Eloquent (≈ l'ORM, il mappatore oggetto↔tabella). Una classe per tabella:
`Tournament`, `Athlete`, `Registration`, `MatchRecord`, `Discipline`, `WeightCategory`, `User`, più
`Media` (allegati). Ognuno dichiara le proprie relazioni, cast e scope di query. Vedi
[Capitolo 06](06-domain-models.md).

**`Models/Scopes/`** — un *global scope* per modello (`TournamentScope`, `AthleteScope`, …). Un global
scope aggiunge automaticamente vincoli a ogni query per quel modello (≈ una clausola `WHERE` di
default). Vedi [Capitolo 07](07-persistence-and-lifecycle.md). La maggior parte sono attualmente
placeholder pass-through.

**`Observers/`** — un observer per modello, collegato con l'attributo `#[ObservedBy(...)]` (≈ un
decoratore/annotazione sulla classe del modello). Gli observer reagiscono agli eventi del ciclo di vita
(`creating`, `updated`, `deleting`, …). Quelli interessanti sono `TournamentObserver` (guardie sulla
cancellazione) e `MatchRecordObserver` (riordino del sort + broadcast WebSocket). Vedi
[Capitolo 07](07-persistence-and-lifecycle.md).

**`Http/Controllers/Api/`** — i gestori delle richieste. Risorse CRUD standard
(`TournamentController`, `AthleteController`, …) più controller annidati
(`TournamentRegistrationController`, `TournamentMatchRecordController`), il flusso di auth
(`AuthController`), il `DashboardController` di sola lettura e i due controller pubblici
(`PublicRegistrationFormController`, `PublicTournamentController`). Vedi [Capitolo 05](05-controllers-and-requests.md).

**`Http/Requests/{Model}/`** — cinque classi FormRequest per modello (`{Model}IndexRequest`,
`ShowRequest`, `StoreRequest`, `UpdateRequest`, `DestroyRequest`). Una FormRequest (≈ un gate di
validazione + autorizzazione che gira *prima* del controller) contiene le regole per una sola azione.
Vedi [Capitolo 05](05-controllers-and-requests.md).

**`Http/Resources/`** — API Resource (≈ serializzatori/DTO di output) che trasformano un modello nella
sua forma JSON. Oggi la maggior parte sono sottili pass-through (`parent::toArray()`), ma sono il punto
di estensione obbligato per modellare l'output.

**`Enums/`** — enum PHP backed per le colonne stringa: `TournamentStatusEnum`,
`MatchRecordStatusEnum`, `MatchRecordEndMethodEnum`, `AthleteGenderEnum`, `TournamentPdfTypeEnum`.
Ognuno ha un metodo `label()` che restituisce una stringa tradotta.

**`Actions/`** — classi stateless che contengono logica complessa estratta da observer/controller.
Oggi: `ReorderMatchRecordsAction` (mantiene contigua la colonna `sort` della card) e
`StoreTemporaryUploadAction` (gestisce upload di file a chunk).

**`Events/`** — `MatchRecordChanged`, l'evento trasmissibile lanciato ogni volta che un incontro cambia.
Vedi [Capitolo 08](08-realtime-files-and-pdf.md).

**`Traits/`** — `InjectWith` (analizza il query param `?with=` per il sideload delle relazioni) e
`InteractsWithMedia` (un wrapper di progetto sopra il trait della Media Library). Vedi
[Capitolo 10](10-notable-patterns.md).

**`Console/Commands/`** — `CleanupTemporaryUploadsCommand`, schedulato in `routes/console.php` per
girare ogni sei ore.

**`Middleware/`** — `SetLocale` (sceglie `en`/`it` dall'header `Accept-Language`) e
`HorizonBasicAuth` (protegge la dashboard di Horizon).

## Come una feature è distribuita

Per rendere concreta l'idea della "distribuzione su layer", ecco la *singola* feature **"crea un match
record sotto un torneo"** e ogni file che tocca:

| File | Ruolo |
|------|-------|
| `routes/api.admin.php` | Dichiara `POST tournaments/{tournament}/match_records`. |
| `Http/Requests/MatchRecord/MatchRecordStoreRequest.php` | Valida il body; inietta `tournament_id` dalla rotta. |
| `Http/Controllers/Api/TournamentMatchRecordController.php` | `store()` — popola e salva il modello. |
| `Models/MatchRecord.php` | L'entità + cast + relazioni. |
| `Observers/MatchRecordObserver.php` | `creating` → riordina; `created` → broadcast. |
| `Actions/ReorderMatchRecordsAction.php` | Mantiene `sort` contiguo. |
| `Events/MatchRecordChanged.php` | Il payload del broadcast. |
| `Http/Resources/MatchRecordResource.php` | Modella la risposta JSON. |

Una volta tracciato questo singolo percorso, ogni altra risorsa segue la stessa forma.
