# 03 — Build, Avvio e Test

> Vedi anche: [09 — Configurazione e Ambiente](09-configuration-env.md), [08 — Real-time, File e PDF](08-realtime-files-and-pdf.md)

Questo capitolo spiega come avviare il progetto in locale e come esercitarlo. L'elenco canonico dei
comandi vive anche in [`CLAUDE.md`](../../CLAUDE.md) e nel [`README.md`](../../README.md); qui si spiega
il *perché* di ogni passo per chi è nuovo a Laravel.

## Tutto passa da Sail

Il progetto usa **Laravel Sail** — un sottile wrapper attorno a `docker compose` che esegue PHP, il
database, Redis e Reverb in container (≈ VM leggere isolate). La regola pratica: **anteponi
`vendor/bin/sail` a ogni comando PHP/Artisan/Composer/Node**. Eseguire `php` direttamente sull'host
userebbe la versione di PHP sbagliata e mancherebbe i servizi.

`artisan` (≈ la CLI del framework, come `manage.py` in Django o `rails`) è il modo per eseguire i task
del framework: migration, elenco delle rotte, suite di test, comandi schedulati.

## Setup iniziale

```bash
composer install                 # installa le dipendenze PHP (necessario per avere Sail stesso)
cp .env.example .env             # crea la config locale dal template
vendor/bin/sail up -d            # avvia i container in background
vendor/bin/sail artisan key:generate   # genera la chiave di cifratura dell'app
vendor/bin/sail artisan migrate  # costruisce lo schema del database
```

Esiste anche uno script `composer setup` (in `composer.json`) che concatena install → env → key →
migrate → npm install → npm build per comodità.

I default di sviluppo locale (vedi [Capitolo 09](09-configuration-env.md)): il database è **SQLite**, e
**sessioni, cache e code usano tutte il driver del database** — quindi non serve Redis solo per
avviare. Redis/Horizon contano quando eserciti i job in background.

## Comandi quotidiani

| Obiettivo | Comando |
|-----------|---------|
| Avvia lo stack | `vendor/bin/sail up -d` |
| Ferma lo stack | `vendor/bin/sail stop` |
| Esegui una migration | `vendor/bin/sail artisan migrate` |
| Elenca le rotte API | `vendor/bin/sail artisan route:list --path=api --except-vendor` |
| Ispeziona un valore di config | `vendor/bin/sail artisan config:show database.default` |
| Formatta il PHP modificato | `vendor/bin/sail bin pint --dirty --format agent` |
| Apri una REPL | `vendor/bin/sail artisan tinker` |

`pint` è il formattatore di codice (≈ Prettier/Black per PHP). Eseguilo prima di finalizzare qualsiasi
modifica PHP.

## Real-time + coda + Horizon

Poiché le modifiche ai match record vengono trasmesse via WebSocket ([Capitolo 08](08-realtime-files-and-pdf.md)),
esercitare a pieno l'app significa eseguire tre cose: il server HTTP, un **worker della coda** (i
broadcast sono job in coda) e il server WebSocket **Reverb**.

Uno script di comodità ne raggruppa due (`composer.json` → `scripts.queue-ws`):

```bash
# esegue `queue:work` e `reverb:start` affiancati
composer queue-ws
```

In produzione la coda è gestita da **Horizon** (`vendor/bin/sail artisan horizon`), che espone anche una
dashboard. Lo scheduler (`routes/console.php`) mantiene fresche le metriche di Horizon con
`horizon:snapshot` ogni cinque minuti ed esegue la pulizia degli upload temporanei ogni sei ore;
entrambi partono dallo scheduler di Laravel (`vendor/bin/sail artisan schedule:work` in locale, o un
singolo cron di sistema in produzione).

```php
// routes/console.php, righe 6-7
Schedule::command(CleanupTemporaryUploadsCommand::class)->everySixHours()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
```

## Asset frontend

C'è un setup Vite (≈ bundler JS) con `resources/js/echo.js` (la config del client WebSocket) e
`resources/css/app.css`. Esistono per supportare la demo del broadcasting e la pagina `welcome`, non
una vera UI. Se mai incontri un errore di *manifest Vite*, esegui `vendor/bin/sail npm run build`.

## Test

La suite usa **PHPUnit 12** (non Pest). I test vivono in `tests/Feature/` (test a livello di richiesta
completa, il tipo di default e preferito) e `tests/Unit/` (test di classe isolati).

```bash
vendor/bin/sail artisan test --compact                       # intera suite
vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php   # un file
vendor/bin/sail artisan test --compact --filter=testName     # un test per nome
```

> **Stato attuale:** esistono solo i placeholder `ExampleTest` scaffoldati in `tests/Feature/` e
> `tests/Unit/`. Le convenzioni in `CLAUDE.md` (usare factory, coprire i percorsi felici/di
> fallimento/limite, scrivere feature test) descrivono come i nuovi test *dovrebbero* essere scritti
> man mano che la logica di dominio guadagna copertura.

Crea nuovi test con `vendor/bin/sail artisan make:test --phpunit {Nome}` (aggiungi `--unit` per un unit
test).
