# Matches API — Documentazione Tecnica

Una visita guidata alle **Matches API**, un backend REST in Laravel 13 per la gestione di tornei di
sport da combattimento: atleti, iscrizioni, card degli incontri e tracciamento live dei match.

Questa documentazione è pensata per uno sviluppatore **competente in programmazione ma nuovo sia a
PHP/Laravel sia al dominio dei tornei di sport da combattimento**. I termini di framework e di dominio
sono spiegati in linea al primo utilizzo (con un'analogia in linguaggio semplice) e raccolti nel
[Glossario](12-glossary.md).

## Indice

| #  | Capitolo | Contenuti |
|----|----------|-----------|
| 00 | [Indice](00-index.md) | Questo file |
| 01 | [Panoramica](01-overview.md) | Cosa fa il sistema, il dominio, lo stack tecnologico, le due superfici di rotte |
| 02 | [Struttura dei Moduli](02-module-structure.md) | Visita di `app/` — ogni layer e cosa vive dove |
| 03 | [Build, Avvio e Test](03-build-run-test.md) | Setup Sail/Docker, migration, suite di test, WebSocket + coda + Horizon |
| 04 | [Ciclo di Vita della Richiesta](04-request-lifecycle.md) | Una richiesta HTTP dall'inizio alla fine, con diagramma di sequenza |
| 05 | [Controller e Request](05-controllers-and-requests.md) | Pattern dei controller, la convenzione delle 5 FormRequest, `InjectWith`, paginazione |
| 06 | [Modelli di Dominio](06-domain-models.md) | Le entità, gli enum e le relazioni, con diagramma ER |
| 07 | [Persistenza e Ciclo di Vita del Modello](07-persistence-and-lifecycle.md) | Chiavi UUID, migration, Observer, Global Scope |
| 08 | [Real-time, File e PDF](08-realtime-files-and-pdf.md) | Broadcasting con Reverb, Media Library / S3, generazione PDF |
| 09 | [Configurazione e Ambiente](09-configuration-env.md) | Variabili d'ambiente, driver, locale, throttling, auth di Horizon |
| 10 | [Pattern Notevoli](10-notable-patterns.md) | Gli idiomi riutilizzabili che definiscono questo codebase |
| 11 | [Dipendenze](11-dependencies.md) | Ogni pacchetto Composer e il ruolo che svolge |
| 12 | [Glossario](12-glossary.md) | Termini Laravel/PHP e di dominio in linguaggio semplice |

### Capitoli non inclusi

- **`ui-navigation`** — non applicabile. Questo è un backend solo-API; serve JSON e non ha schermate
  rivolte all'utente. Le uniche view Blade (≈ template HTML) esistenti sono i PDF renderizzati lato
  server e una email transazionale, trattati nel [Capitolo 08](08-realtime-files-and-pdf.md).

## Come Leggere Questo Libro

- I capitoli possono essere letti in ordine; ognuno include riferimenti incrociati all'inizio.
- Tutti i percorsi sono relativi alla radice del repository.
- I termini specifici del linguaggio sono spiegati in linea al primo uso e raccolti nel Glossario.
- Le convenzioni di progetto vivono anche in [`CLAUDE.md`](../../CLAUDE.md) e [`README.md`](../../README.md);
  questo libro li collega invece di ripeterli.

## Convenzioni di Notazione

- `Codice` → identificatori, nomi di file, comandi.
- *corsivo* → concetti di dominio.
- → / ⇆ → direzione del flusso dati (unidirezionale / bidirezionale).
- `termine (≈ analogia)` → un termine di framework/dominio spiegato al primo uso.
