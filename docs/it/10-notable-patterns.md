# 10 — Pattern Notevoli

> Vedi anche: [05 — Controller e Request](05-controllers-and-requests.md), [07 — Persistenza e Ciclo di Vita del Modello](07-persistence-and-lifecycle.md), [04 — Ciclo di Vita della Richiesta](04-request-lifecycle.md)

Questo capitolo raccoglie gli idiomi ricorrenti che danno carattere al codebase. Riconoscili e i nuovi
file diventano prevedibili; seguili quando aggiungi feature così il codice resta uniforme.

## 1. La pipeline uniforme delle risorse

Quasi ogni risorsa è lo stesso controller a cinque metodi + cinque FormRequest + una Resource + un
Model + un Observer + uno Scope. Aggiungere una nuova risorsa significa riempire lo stesso insieme di
slot — non inventare una struttura. La skill `.claude/skills/laravel-scaffold/` esiste proprio per
generare l'intero set di artefatti da uno schema. **Nel dubbio, copia la forma di una risorsa
esistente.**

## 2. `InjectWith` — sideload sicuro delle relazioni

Il trait che alimenta `?with=relation1,relation2`. Vive in `app/Traits/InjectWith.php`, gira in
`prepareForValidation`, divide + camelCasa la stringa in un array, e la request valida quell'array
contro una **allowlist** (`with.*` → `Rule::in([...])`). Il controller poi fa l'eager loading esatto di
quelle relazioni. Il valore del pattern è che *il client controlla l'eager loading senza poter caricare
relazioni arbitrarie* — l'allowlist è il confine di sicurezza. Vedi [Capitolo 05](05-controllers-and-requests.md).

Quando una request ha bisogno sia di `InjectWith` sia del proprio `prepareForValidation`, fa l'alias del
metodo del trait (`prepareForValidation as injectWithPrepare`) e lo chiama per primo. Riusa esattamente
questo idioma invece di duplicare la logica di parsing del `with`.

## 3. Ereditarietà `StoreRequest` ← `UpdateRequest`

Quando create e update condividono le regole, la update request è una riga sola:
`class XUpdateRequest extends XStoreRequest {}`. Le regole sono definite una volta. Diverge solo
sovrascrivendo `rules()` nella sottoclasse quando l'update è davvero diverso.

## 4. Iniezione di chiavi di rotta annidate

Le risorse figlie non si fidano mai di un id genitore nel body. La store request lo inietta dal genitore
legato alla rotta in `prepareForValidation`:

```php
// app/Http/Requests/Registration/RegistrationStoreRequest.php, righe 40-42
$this->merge([
    'tournament_id' => $this->input('tournament_id', $this->tournament?->id),
]);
```

Questo mantiene `tournaments/{tournament}/registrations` consistente e non falsificabile.

## 5. Gli Observer possiedono gli effetti collaterali; le Action la logica complessa

I controller restano sottili. Tutto ciò che è innescato dalla persistenza (guardie di cancellazione,
manutenzione del sort, broadcast real-time) vive nell'**observer** del modello; tutto ciò che è
algoritmicamente impegnativo viene sollevato ancora più in alto in una classe **Action** stateless che
l'observer chiama. `MatchRecordObserver` → `ReorderMatchRecordsAction` è l'esempio canonico. La nuova
logica trasversale dovrebbe seguire la stessa divisione:

```
Observer (quando)  →  Action (come, in una transazione)
```

## 6. Broadcasting "segnale, non stato"

`MatchRecordChanged` trasmette solo `{"refresh": true}` dopo il commit, e il client ri-recupera
attraverso il normale endpoint. Combinato con `ShouldDispatchAfterCommit` e un canale pubblico per
torneo, questo mantiene la consegna real-time minuscola, consistente e priva di serializzazione
duplicata. Usa questa forma per qualsiasi futura risorsa real-time (il `CLAUDE.md` lo raccomanda
esplicitamente).

## 7. Enum come colonna stringa con un `label()`

Ogni colonna stato/tipo/metodo è una `string` nel DB, castata a un enum PHP backed nel modello, con
l'enum che espone un `label()` tradotto. Niente tipi `ENUM` a livello DB. Questo mantiene le migration
portabili e la localizzazione centralizzata. Vedi [Capitolo 06](06-domain-models.md) /
[Capitolo 07](07-persistence-and-lifecycle.md).

## 8. Scope nominati per l'intento di query riutilizzabile

La logica di filtraggio è espressa come metodi `#[Scope]` sul modello (`search`, `unpaid`, `unarrived`,
`weightInExceeded`, `inTournament`) invece di clausole `where` in linea sparse tra i controller. Il
controller legge un parametro validato e chiama lo scope. Metti qui i nuovi predicati di query
riutilizzabili.

## 9. Aggrega nel database, non in PHP

`DashboardController` usa `withCount` con closure condizionali per calcolare ogni statistica per torneo
in una singola query, invece di caricare collection e contare in PHP:

```php
// app/Http/Controllers/Api/DashboardController.php, righe 20-29 (estratto)
->withCount([
    'registrations as totalRegistrations',
    'registrations as paidRegistrations' => fn ($q) => $q->whereNotNull('paid_at'),
    'matchRecords as completedMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::COMPLETED),
    // ...
])
```

## 10. Transazioni attorno alle operazioni multi-scrittura

Ogni volta che più di una riga cambia insieme — il riordino del sort, o un create di torneo che allega
anche un'immagine di copertina — il lavoro è avvolto in una transazione (`DB::transaction(...)`
nell'Action, o `DB::beginTransaction()/commit()` espliciti in `TournamentController`). Questo garantisce
che la card non finisca mai riordinata a metà e che un torneo non sia mai salvato senza il media
previsto.

## 11. `saveOrFail()` invece di `save()`

I controller persistono con `saveOrFail()`, che lancia un'eccezione in caso di fallimento invece di
restituire un booleano. Abbinato al rendering JSON delle eccezioni in `bootstrap/app.php`, questo
trasforma qualsiasi problema di persistenza in un errore JSON pulito invece di un `false` ignorato in
silenzio.
