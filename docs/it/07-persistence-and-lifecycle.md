# 07 — Persistenza e Ciclo di Vita del Modello

> Vedi anche: [06 — Modelli di Dominio](06-domain-models.md), [04 — Ciclo di Vita della Richiesta](04-request-lifecycle.md), [10 — Pattern Notevoli](10-notable-patterns.md)

Questo capitolo copre come i dati sono memorizzati e come l'applicazione reagisce ai cambiamenti del
modello: chiavi UUID, migration, cast, **observer** (hook del ciclo di vita) e **global scope** (vincoli
di query di default).

## Eloquent in un paragrafo

**Eloquent** è l'ORM di Laravel (≈ object-relational mapper — il layer che mappa una riga del database a
un oggetto PHP). Ogni tabella ha una classe modello; `Model::find($id)` carica una riga, `$model->save()`
persiste le modifiche, e i metodi di relazione (`hasMany`, `belongsTo`) esprimono le chiavi esterne.
Raramente scrivi SQL direttamente — componi una query attraverso il builder fluido
(`Tournament::where(...)->orderBy(...)->get()`).

## Chiavi primarie UUID ovunque

Un'invariante rigida: **tutte le chiavi primarie ed esterne sono UUID**, non interi auto-incrementanti.
I modelli aderiscono con il trait `HasUuids`, e le migration dichiarano `uuid('id')->primary()` e
`foreignUuid(...)->constrained(...)`:

```php
// database/migrations/2026_06_15_100006_create_match_records_table.php, righe 12-17
$table->uuid('id')->primary();
$table->foreignUuid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
$table->foreignUuid('red_corner_id')->constrained('athletes')->cascadeOnDelete();
$table->foreignUuid('blue_corner_id')->constrained('athletes')->cascadeOnDelete();
$table->foreignUuid('weight_category_id')->constrained('weight_categories')->cascadeOnDelete();
$table->foreignUuid('discipline_id')->constrained('disciplines')->cascadeOnDelete();
```

Poiché le chiavi esterne UUID arrivano dal JSON come stringhe, i modelli castano anche le colonne id a
`'string'` (vedi `MatchRecord::casts()`), mantenendo i tipi prevedibili.

## Cast — tipizzare le colonne

Il metodo `casts()` di un modello dichiara come i valori grezzi del DB diventano valori PHP. Qui contano
tre stili di cast:

| Cast | Effetto |
|------|---------|
| `'date'` / `'datetime'` | Oggetti data Carbon (≈ un DateTime ricco). |
| `'boolean'` / `'float'` | Tipi scalari nativi. |
| `EnumClass::class` | Un enum PHP backed (la regola enum-come-stringa). |
| `'array'` | Colonna JSON ↔ array PHP (es. `judges_points`). |

```php
// app/Models/MatchRecord.php, righe 45-60 (estratto)
'gender' => AthleteGenderEnum::class,
'end_method' => MatchRecordEndMethodEnum::class,
'status' => MatchRecordStatusEnum::class,
'judges_points' => 'array',
```

## Migration

Le migration (`database/migrations/`) sono script di schema ordinati e versionati — ognuna ha `up()`
(applica) e `down()` (annulla). Il timestamp nel nome del file imposta l'ordine. Le chiavi esterne usano
regole di cascade: `cascadeOnDelete()` (cancella i figli col genitore) per gli angoli e i riferimenti,
ma `nullOnDelete()` per `winner_id` (cancellare un atleta azzera il puntatore al vincitore invece di
eliminare l'incontro). Eseguile con `vendor/bin/sail artisan migrate`.

## Observer — reagire al ciclo di vita del modello

Un **observer** è una classe i cui metodi si attivano sugli eventi del ciclo di vita di un modello:
`creating`/`created`, `updating`/`updated`, `deleting`/`deleted`. Le varianti `*ing` girano *prima*
della scrittura nel DB (e possono annullarla o mutarla); le varianti `*ed` girano *dopo*. Ogni modello è
legato al suo observer con un attributo:

```php
// app/Models/Tournament.php, riga 19
#[ObservedBy([TournamentObserver::class])]
```

La maggior parte degli observer sono scaffoldati con metodi vuoti. Due portano logica reale.

### TournamentObserver — guardie sulla cancellazione

Impedisce di cancellare un torneo che ha ancora dati dipendenti, restituendo HTTP `409 Conflict` con un
messaggio tradotto:

```php
// app/Observers/TournamentObserver.php, righe 17-21
public function deleting(Tournament $tournament): void
{
    abort_if($tournament->registrations()->exists(), 409, __('errors.tournament_has_registrations'));
    abort_if($tournament->matchRecords()->exists(), 409, __('errors.tournament_has_match_records'));
}
```

### MatchRecordObserver — sort + broadcast

L'observer più indaffarato. Delega la manutenzione del `sort` a un'Action ed emette l'evento real-time:

```php
// app/Observers/MatchRecordObserver.php, righe 11-39 (estratto)
public function creating(MatchRecord $matchRecord): void  { ReorderMatchRecordsAction::handleCreating($matchRecord); }
public function created(MatchRecord $matchRecord): void   { event(new MatchRecordChanged($matchRecord)); }
public function updating(MatchRecord $matchRecord): void  { ReorderMatchRecordsAction::handleUpdating($matchRecord); }
public function updated(MatchRecord $matchRecord): void   { event(new MatchRecordChanged($matchRecord)); }
public function deleting(MatchRecord $matchRecord): void  { ReorderMatchRecordsAction::handleDeleting($matchRecord); }
public function deleted(MatchRecord $matchRecord): void   { event(new MatchRecordChanged($matchRecord)); }
```

### L'Action di riordino del sort

`ReorderMatchRecordsAction` mantiene la card di ogni torneo come una sequenza **contigua** `1..N` man
mano che gli incontri vengono inseriti, spostati o rimossi — tutto dentro transazioni DB così la card
non finisce mai con buchi o posizioni duplicate. All'inserimento, se non viene richiesta alcuna
posizione (o una oltre la fine) accoda; altrimenti sposta tutto a partire dalla posizione target verso
l'alto di uno:

```php
// app/Actions/ReorderMatchRecordsAction.php, righe 18-29 (estratto)
if ($requestedSort === null || $requestedSort > $max) {
    $matchRecord->sort = $max + 1;
    return;
}
MatchRecord::withoutGlobalScopes([MatchRecordScope::class])
    ->where('tournament_id', $tournamentId)
    ->where('sort', '>=', $requestedSort)
    ->increment('sort');
$matchRecord->sort = $requestedSort;
```

La gestione dell'update sposta le righe intermedie nella direzione in cui l'incontro si è mosso; la
gestione della cancellazione chiude il buco decrementando tutto ciò che segue la posizione rimossa. Nota
il deliberato `withoutGlobalScopes(...)` — il riordino deve vedere *ogni* riga del torneo, non un
sottoinsieme con scope applicato.

## Global scope — vincoli di query di default

Un **global scope** aggiunge automaticamente un vincolo a *ogni* query per un modello (≈ un `WHERE` di
default applicato salvo rimozione esplicita). Ogni modello è legato a uno via `#[ScopedBy([...])]`. In
questo codebase gli scope sono attualmente **placeholder pass-through** — ispezionano l'utente
autenticato ma non aggiungono ancora alcun vincolo:

```php
// app/Models/Scopes/TournamentScope.php, righe 11-18
public function apply(Builder $builder, Model $model): void
{
    $user = auth()->user();
    if ($user === null) {
        return;
    }
}
```

Esistono come hook designato per future regole di multi-tenancy o visibilità. La conseguenza pratica
importante è già visibile nell'Action di riordino: quando la logica deve aggirare questi scope, chiama
`withoutGlobalScopes([...])`.

## Scope di query nominati

Separatamente dai global scope, i modelli definiscono scope **nominati** con l'attributo `#[Scope]` —
frammenti di query riutilizzabili a cui aderisci. Lo scope `search` (su quasi ogni modello) alimenta il
parametro `?search=`; `Registration` aggiunge `unpaid`, `unarrived`, `weightInExceeded`; `Athlete`
aggiunge `inTournament`. Vedi il [Capitolo 06](06-domain-models.md) per cosa fa ciascuno.
