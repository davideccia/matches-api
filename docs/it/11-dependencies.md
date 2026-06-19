# 11 — Dipendenze

> Vedi anche: [08 — Real-time, File e PDF](08-realtime-files-and-pdf.md), [09 — Configurazione e Ambiente](09-configuration-env.md), [12 — Glossario](12-glossary.md)

Questo capitolo elenca i pacchetti di terze parti del progetto (da `composer.json`) e il ruolo concreto
che ciascuno svolge. Le dipendenze sono gestite da **Composer** (≈ npm/pip per PHP); `composer.json` è
il manifest e `composer.lock` fissa le versioni esatte.

## Dipendenze di runtime (`require`)

| Pacchetto | Versione | Ruolo in questo progetto |
|-----------|----------|--------------------------|
| `php` | `^8.3` | Runtime del linguaggio. Usa feature moderne: enum backed, promozione delle proprietà nel costruttore, attributi, `#[\SensitiveParameter]`. |
| `laravel/framework` | `^13.8` | Il framework stesso — routing, ORM Eloquent, validazione, code, scheduler, broadcasting. |
| `laravel/sanctum` | `^4.0` | Autenticazione a token API per la superficie `/api/admin`. Fornisce `HasApiTokens`, `auth:sanctum`, le ability dei token. |
| `laravel/reverb` | `^1.0` | Server WebSocket nativo che consegna i broadcast di `MatchRecordChanged`. Vedi [Capitolo 08](08-realtime-files-and-pdf.md). |
| `laravel/horizon` | `^5.47` | Dashboard + supervisore per le code Redis in produzione; esegue i worker di broadcast/job. Snapshot ogni 5 min dallo scheduler. |
| `laravel/tinker` | `^3.0` | REPL interattiva (`artisan tinker`) per eseguire PHP nel contesto dell'app. |
| `spatie/laravel-pdf` | `^2.12` | Renderizza view Blade → PDF (conferme di iscrizione, card degli incontri). |
| `dompdf/dompdf` | `^3.1` | Il motore di rendering HTML→PDF che `spatie/laravel-pdf` pilota. |
| `spatie/laravel-medialibrary` | `^11.23` | Allega file caricati (copertina del torneo) ai modelli Eloquent; il modello `Media` + tabella `media`. |
| `league/flysystem-aws-s3-v3` | `^3.34` | Adapter filesystem S3 così che media/file possano vivere su Amazon S3 in produzione. |
| `spatie/laravel-responsecache` | `^8.4` | Caching dell'intera risposta HTTP (disponibile; applicabile agli endpoint di lettura cacheabili). |

## Dipendenze di sviluppo (`require-dev`)

| Pacchetto | Versione | Ruolo |
|-----------|----------|-------|
| `phpunit/phpunit` | `^12.5` | L'esecutore dei test (feature + unit). |
| `laravel/sail` | `^1.62` | Ambiente dev Docker; il comando `vendor/bin/sail`. |
| `laravel/pint` | `^1.29` | Formattatore di codice opinionato (≈ Prettier/Black per PHP). |
| `laravel/pail` | `^1.2.5` | Segue i log applicativi in tempo reale (`artisan pail`). |
| `laravel/boost` | `^2.4` | Tooling MCP per lo sviluppo assistito da AI (ricerca docs, schema/query DB, log). |
| `laravel/pao` | `^1.0.6` | Tooling di sviluppo Laravel. |
| `fakerphp/faker` | `^1.23` | Genera dati finti per factory/seeder nei test. |
| `mockery/mockery` | `^1.6` | Libreria di mocking per gli unit test. |
| `nunomaduro/collision` | `^8.6` | Reporting degli errori CLI elegante per test/console. |

## Tooling frontend

Sebbene non ci sia una vera UI, esiste una toolchain JS minimale per il client WebSocket e la pagina
demo: **Vite** (bundler) con **Laravel Echo** configurato in `resources/js/echo.js` per connettersi a
Reverb. Eseguilo con `vendor/bin/sail npm run build` / `dev`.

## Script composer di comodità

Definiti in `composer.json`:

- `composer setup` — install → copia env → key:generate → migrate → npm install → npm build.
- `composer queue-ws` — esegue `queue:work` e `reverb:start` affiancati (via `concurrently`) per lo
  sviluppo real-time in locale.

## Cambiare le dipendenze

Secondo [`CLAUDE.md`](../../CLAUDE.md), **non** aggiungere o cambiare dipendenze senza approvazione, e
installa sempre attraverso Sail: `vendor/bin/sail composer require vendor/package`.
