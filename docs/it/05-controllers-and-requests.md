# 05 — Controller e Request

> Vedi anche: [04 — Ciclo di Vita della Richiesta](04-request-lifecycle.md), [10 — Pattern Notevoli](10-notable-patterns.md), [06 — Modelli di Dominio](06-domain-models.md)

Questo capitolo copre il layer di gestione HTTP: come sono strutturati i controller, la rigida
convenzione delle cinque FormRequest per modello e le funzionalità di query trasversali (`?with=`,
paginazione, ricerca) che ogni endpoint di lista/dettaglio condivide.

## Catalogo dei controller

| Controller | Superficie | Note |
|------------|------------|------|
| `TournamentController` | admin | CRUD completo `apiResource`. |
| `AthleteController` | admin | CRUD completo. |
| `RegistrationController` | admin | CRUD completo + azione `pdf()`. |
| `MatchRecordController` | admin | CRUD completo. |
| `DisciplineController`, `WeightCategoryController` | admin + pubblica | Dati di riferimento; `index` esposto anche pubblicamente. |
| `UserController` | admin | CRUD degli account organizzatore. |
| `TournamentRegistrationController` | admin | Annidato: `index` + `store` sotto un torneo. |
| `TournamentMatchRecordController` | admin | Annidato: `index` + `store` + `matchRecordsPdf`. |
| `AuthController` | admin | `login`, `user`, `logout`, `forgotPassword`, `resetPassword`. |
| `DashboardController` | admin | Conteggi aggregati di sola lettura per torneo attivo. |
| `PublicRegistrationFormController` | pubblica | Ricerca/upsert atleta, elenco tornei aperti, auto-iscrizione, PDF iscrizione. |
| `PublicTournamentController` | pubblica | Elenco pubblico tornei + card pubblica degli incontri. |
| `TemporaryUploadController`, `MediaController` | admin | Upload a chunk e download dei media. |

`apiResource` (un helper di rotte di Laravel) mappa i cinque verbi REST sui cinque metodi del
controller: `index` (GET lista), `store` (POST crea), `show` (GET uno), `update` (PUT/PATCH), `destroy`
(DELETE).

## La convenzione delle cinque FormRequest

Ogni modello ha **esattamente cinque** classi FormRequest sotto `app/Http/Requests/{Model}/`, una per
azione: `{Model}IndexRequest`, `{Model}ShowRequest`, `{Model}StoreRequest`, `{Model}UpdateRequest`,
`{Model}DestroyRequest`. Una FormRequest raggruppa **autorizzazione** (`authorize()`) e **validazione**
(`rules()`) per una singola azione, e gira automaticamente prima del controller (vedi
[Capitolo 04](04-request-lifecycle.md)).

Una scorciatoia ricorrente: **`UpdateRequest` estende `StoreRequest`** quando le regole sono identiche,
così le regole di update sono definite una sola volta:

```php
// app/Http/Requests/MatchRecord/MatchRecordUpdateRequest.php (intero file)
class MatchRecordUpdateRequest extends MatchRecordStoreRequest {}
```

L'autorizzazione è volutamente semplice a questo layer: le request admin usano
`return auth()->hasUser();` (qualsiasi organizzatore autenticato può agire); regole più granulari, dove
esistono, vivono invece nel layer di dominio/observer.

## Funzionalità di query trasversali

Ogni request index/show/store supporta le stesse tre famiglie di query parameter. Sono l'"ergonomia
API" del progetto.

### Sideload delle relazioni — `?with=`

I client scelgono quali record correlati incorporare tramite un parametro `with` separato da virgole,
es. `?with=redCorner,blueCorner,winner`. Il trait `InjectWith` lo normalizza:

```php
// app/Traits/InjectWith.php, righe 9-16
protected function prepareForValidation(): void
{
    $with = collect($this->with ? explode(',', $this->with) : [])
        ->map(fn ($w) => Str::camel($w))
        ->all();

    $this->merge(['with' => $with]);
}
```

La stringa viene divisa, ogni nome convertito in camelCase, e l'array risultante è validato contro una
**allowlist** nella regola `with.*` della request:

```php
// app/Http/Requests/MatchRecord/MatchRecordIndexRequest.php, righe 21-29
'with' => ['nullable', 'array'],
'with.*' => [Rule::in([
    'tournament', 'redCorner', 'blueCorner', 'winner', 'weightCategory', 'discipline',
])],
```

Il controller poi fa l'eager loading esattamente di quelle relazioni con
`->with($validated['with'] ?? [])` (liste) o `->loadMissing($validated['with'] ?? [])` (record
singoli). L'allowlist è il confine di sicurezza — un client non può caricare una relazione arbitraria.
Nota che le request **store/update** spesso non permettono *nessuna* relazione (`Rule::in([])`), quindi
`?with=` è soprattutto una funzionalità di index/show.

Quando una request usa sia `InjectWith` *sia* ha bisogno del proprio `prepareForValidation` (per
iniettare una chiave di rotta), fa l'alias del metodo del trait e lo chiama esplicitamente:

```php
// app/Http/Requests/MatchRecord/MatchRecordStoreRequest.php, righe 15-17, 50-57
use InjectWith {
    prepareForValidation as injectWithPrepare;
}
// ...
protected function prepareForValidation(): void
{
    $this->injectWithPrepare();
    $this->merge(['tournament_id' => $this->input('tournament_id', $this->tournament?->id)]);
}
```

### Paginazione

Le request index espongono `paginate` (bool), `per_page` e `page`. Il controller si dirama in base ad
esso:

```php
// app/Http/Controllers/Api/TournamentController.php, righe 28-32
if ($validated['paginate'] ?? false) {
    $tournaments = $tournaments->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
} else {
    $tournaments = $tournaments->get();
}
```

`paginate=true` restituisce un envelope paginato (dati + meta); altrimenti l'intera collection.

### Ricerca

Una stringa `search` nullable attiva lo scope di query `search` del modello (vedi
[Capitolo 07](07-persistence-and-lifecycle.md)), che esegue match case-insensitive con `ilike` sulle
colonne/relazioni rilevanti:

```php
// app/Http/Controllers/Api/TournamentController.php, righe 24-26
if (isset($validated['search'])) {
    $tournaments->search($validated['search']);
}
```

## Iniezione di chiavi di rotta annidate

Per le rotte annidate come `tournaments/{tournament}/match_records`, l'id del genitore **non** è nel
body della richiesta — viene dall'URL. La store request lo inietta in `prepareForValidation` (mostrato
sopra) leggendo `$this->tournament` (il modello legato alla rotta), così il client non deve mai inviare
un `tournament_id` ridondante e non può falsificarne uno diverso.

## Due controller non-CRUD da conoscere

- **`AuthController`** — `login()` verifica le credenziali con `auth()->attempt()` poi emette un token
  Sanctum via `$user->createToken('api')->plainTextToken`; `logout()` cancella il token corrente;
  `forgotPassword`/`resetPassword` usano il broker `Password` di Laravel e inviano via email un link di
  reset costruito da `app.frontend_url`.
- **`DashboardController`** — un singolo `index()` di sola lettura che restituisce i tornei attivi con
  aggregati `withCount` (iscrizioni totali/arrivati/pagati, match per stato). È l'unico punto che usa
  subquery di conteggio condizionali, e un buon esempio di spingere l'aggregazione nel database invece
  di contare in PHP.
