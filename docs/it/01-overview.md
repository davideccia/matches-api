# 01 — Panoramica

> Vedi anche: [02 — Struttura dei Moduli](02-module-structure.md), [06 — Modelli di Dominio](06-domain-models.md), [12 — Glossario](12-glossary.md)

Questo capitolo spiega a cosa serve il sistema, il dominio che modella, la tecnologia su cui è
costruito e il fatto strutturale più importante della sua superficie HTTP: espone **due aree API
separate** con regole di sicurezza diverse.

## Cosa fa il sistema

Le Matches API sono il backend per organizzare **tornei di sport da combattimento** (boxe, kickboxing,
eventi in stile MMA). Un organizzatore di tornei lo usa per:

- creare un *torneo* e farlo avanzare nel suo ciclo di vita (programmato → iscrizioni aperte → in corso
  → completato);
- mantenere un registro di *atleti*, ognuno identificato pubblicamente da un *codice fiscale* (≈ un
  identificativo fiscale nazionale);
- registrare le *iscrizioni* — un atleta che entra in uno specifico torneo in una specifica *disciplina*
  e *categoria di peso*, tracciando se ha pagato, se è arrivato e se ha rispettato il peso;
- costruire la *card degli incontri* — l'elenco ordinato di *match record* (incontri), ciascuno che
  abbina un atleta dell'angolo rosso e uno dell'angolo blu, con round, punteggi dei giudici, metodo di
  conclusione e vincitore;
- trasmettere in tempo reale le modifiche alla card via WebSocket, così che uno schermo segnapunti si
  aggiorni in tempo reale;
- esporre una piccola area **pubblica** così che gli atleti possano cercarsi e auto-iscriversi senza
  effettuare il login, e scaricare un PDF di iscrizione.

È **solo-API** — restituisce JSON, non ha una UI web propria ed è pensato per essere consumato da
un'applicazione frontend separata (una single-page app, il cui URL è configurato come
`app.frontend_url`).

## Stack tecnologico

| Layer | Tecnologia | Spiegazione |
|-------|-----------|-------------|
| Linguaggio | PHP 8.3+ | Il linguaggio lato server. |
| Framework | Laravel 13 | Il web framework (≈ ciò che Spring è per Java, o NestJS per Node) — routing, ORM, validazione, code. |
| Autenticazione | Laravel Sanctum 4 | Emette token API (≈ bearer token) per l'area autenticata. |
| Real-time | Laravel Reverb | Un server WebSocket nativo per inviare eventi live ai client. |
| Database | SQLite (locale), query compatibili PostgreSQL | In locale è SQLite; le query usano `ilike` (match case-insensitive in stile Postgres). |
| Job in background | Laravel Horizon | Dashboard + supervisore per i job in coda eseguiti su Redis. |
| PDF | `spatie/laravel-pdf` (+ `dompdf`) | Renderizza view Blade in file PDF. |
| Storage file | `spatie/laravel-medialibrary` (+ S3) | Allega file caricati (es. un'immagine di copertina del torneo) ai modelli. |
| Ambiente dev | Laravel Sail | Stack di sviluppo Docker (≈ containerizzato); tutti i comandi passano da qui. |
| Testing | PHPUnit 12 | L'esecutore dei test. |

Vedi il [Capitolo 11](11-dependencies.md) per il ruolo di ogni pacchetto.

## Le due superfici API

Questo è il fatto strutturale da interiorizzare per primo. Il routing è configurato in
`bootstrap/app.php` (≈ il file di avvio/configurazione dell'applicazione), che monta **due** file di
rotte sotto **due** prefissi URL:

```php
// bootstrap/app.php, righe 17-29 (closure then:)
Route::middleware('api')
    ->prefix('api/admin')
    ->group(base_path('routes/api.admin.php'));

Route::middleware(['api', 'throttle:10,1'])
    ->prefix('api/public')
    ->group(base_path('routes/api.public.php'));
```

| Superficie | Prefisso URL | File | Auth | Scopo |
|------------|-------------|------|------|-------|
| **Admin** | `/api/admin/*` | `routes/api.admin.php` | Token Sanctum (quasi tutte le rotte) | CRUD completo per gli organizzatori: tornei, atleti, iscrizioni, match record, utenti. |
| **Pubblica** | `/api/public/*` | `routes/api.public.php` | Nessuna (rate-limited) | Self-service atleta: ricerca per codice fiscale, auto-iscrizione, elenco tornei aperti, download PDF di iscrizione. |

> **Nota — le rotte *non* sono versionate.** Nonostante in `CLAUDE.md` si menzioni `/api/v1/`, non c'è
> alcun segmento di versione negli URL. I prefissi reali sono `/api/admin` e `/api/public`. La rotta
> radice `routes/api.php` è un placeholder vuoto; i due file sopra sono montati esplicitamente nella
> closure `then:` di `withRouting()`.

La superficie pubblica è soggetta a throttling globale (`throttle:10,1` ≈ 10 richieste al minuto per
client). La superficie admin si appoggia a Sanctum: in `routes/api.admin.php` solo il login e gli
endpoint di reset password sono aperti; tutto il resto vive dentro un
`Route::middleware(['auth:sanctum'])->group(...)`.

## Un modello mentale di una richiesta

Ogni richiesta JSON attraversa la stessa pipeline a layer (dettagliata nel
[Capitolo 04](04-request-lifecycle.md)):

```
richiesta HTTP
  → rotta (api.admin.php / api.public.php)
  → middleware (SetLocale, Sanctum auth, throttle)
  → FormRequest (autorizza + valida input)
  → Controller (orchestra)
  → Model + Observer + Action (logica di business, DB)
  → API Resource (modella il JSON)
  → risposta JSON
```

Se capisci che questa pipeline è *la stessa* per quasi ogni endpoint — cambiano solo il modello e la
specifica FormRequest/Resource — hai capito l'80% del codebase. Il restante 20% è la logica
specifica di dominio nel [Capitolo 06](06-domain-models.md), nel [Capitolo 07](07-persistence-and-lifecycle.md)
e i pattern nel [Capitolo 10](10-notable-patterns.md).
