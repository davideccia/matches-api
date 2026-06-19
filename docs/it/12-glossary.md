# 12 — Glossario

> Vedi anche: tutti i capitoli.

Termini specifici di PHP/Laravel e del dominio dei tornei di sport da combattimento, spiegati per uno
sviluppatore che non ha lavorato con nessuno dei due.

## Termini PHP / Laravel

| Termine | Significato in linguaggio semplice |
|---------|-------------------------------------|
| **Laravel** | Il web framework su cui è costruita questa app — fornisce routing, ORM, validazione, code, broadcasting. Paragonabile a Spring (Java), Rails (Ruby) o NestJS (Node). |
| **Artisan** | Lo strumento a riga di comando di Laravel (`php artisan ...`). Usato per migration, elenco rotte, test, generazione di file. Come `manage.py` in Django o `rails` in Rails. |
| **Composer** | Il gestore di dipendenze di PHP. `composer.json` è il manifest, `composer.lock` fissa le versioni. Come npm (Node) o pip (Python). |
| **Sail** | L'ambiente dev locale di Laravel basato su Docker. Tutti i comandi sono preceduti da `vendor/bin/sail`. |
| **Eloquent** | L'ORM di Laravel (object-relational mapper): ogni tabella DB è una classe PHP, ogni riga un oggetto. `Model::find()`, `$model->save()`, `hasMany()`. |
| **Migration** | Uno script versionato che costruisce/altera lo schema del database, con i metodi `up()` e `down()`. Eseguito in ordine di timestamp del nome file. |
| **Model** | Una classe Eloquent che rappresenta una tabella; dichiara relazioni, cast e scope. |
| **Cast** | Una dichiarazione che converte un valore grezzo del DB in un tipo PHP (`'date'`, `'boolean'`, una classe enum, `'array'` per JSON) e viceversa. |
| **Relazione** | Un metodo (`hasMany`, `belongsTo`, `morphOne`) che descrive un collegamento a chiave esterna tra modelli, permettendo di percorrere `$tournament->registrations`. |
| **Eager loading** | Pre-caricare i record correlati in una sola query per evitare il **problema N+1** (una query per riga). Fatto via `with()` / `loadMissing()`. |
| **Problema N+1** | Un bug di performance in cui iterare su N record innesca N query extra; risolto con l'eager loading. |
| **Controller** | La classe i cui metodi gestiscono le richieste HTTP per una risorsa (`index`, `store`, `show`, `update`, `destroy`). |
| **`apiResource`** | Un helper di rotte che mappa i cinque verbi REST su quei cinque metodi del controller in una riga. |
| **FormRequest** | Una classe che valida e autorizza una richiesta *prima* che il controller giri. Contiene `authorize()` e `rules()`. |
| **Regole di validazione** | Vincoli dichiarativi sull'input (`required`, `uuid`, `exists:tabella,col`, `Rule::in([...])`). Il fallimento → errore JSON `422` automatico. |
| **API Resource** | Un serializzatore di output che trasforma un modello nella sua forma JSON. Il layer di risposta obbligato qui. |
| **Middleware** | Un filtro che avvolge la richiesta/risposta (auth, locale, throttling). Come i middleware di Express o i filtri servlet. |
| **Route-model binding** | Lookup automatico che trasforma un segmento URL (`{tournament}`, o `{athlete:tax_number}`) in un modello caricato prima che il controller giri. |
| **Observer** | Una classe con metodi che si attivano sugli eventi del ciclo di vita di un modello (`creating`, `updated`, `deleting`). Legato via `#[ObservedBy(...)]`. |
| **Global scope** | Un vincolo auto-aggiunto a *ogni* query per un modello (un `WHERE` di default). Legato via `#[ScopedBy(...)]`; aggirato con `withoutGlobalScopes()`. |
| **Scope nominato** | Un frammento di query riutilizzabile definito con `#[Scope]` a cui aderisci (`->search($q)`, `->unpaid(true)`). |
| **Trait** | Un insieme riutilizzabile di metodi mescolato in una classe (`use HasUuids;`). La forma di riuso orizzontale del codice / mixin di PHP. |
| **Action** | Una convenzione di progetto: una classe stateless che contiene una operazione complessa, chiamata da observer/controller (es. `ReorderMatchRecordsAction`). |
| **Event / Broadcasting** | Un oggetto dispacciato quando accade qualcosa; se è `ShouldBroadcast`, viene spinto ai client via WebSocket. |
| **Reverb** | Il server WebSocket nativo di Laravel (il backend che spinge gli eventi). |
| **Echo** | La libreria client JavaScript di Laravel che si sottoscrive ai canali di broadcast. |
| **Channel (Canale)** | Uno stream nominato su cui gli eventi vengono trasmessi; i client si sottoscrivono. I canali pubblici non richiedono auth; quelli privati sono autorizzati in `channels.php`. |
| **Queue / worker (Coda)** | Elaborazione di job in background. Il lavoro in coda (inclusi i broadcast) è eseguito da un worker `queue:work`; gestito da Horizon in produzione. |
| **Horizon** | Una dashboard + supervisore di processi per le code basate su Redis. |
| **Sanctum** | Il pacchetto di auth a token API di Laravel. Emette bearer token; `auth:sanctum` protegge le rotte. |
| **Blade** | Il linguaggio di templating HTML di Laravel. Usato qui solo per i layout PDF e una email — nessuna UI. |
| **Carbon** | L'oggetto data/ora che Laravel restituisce per i cast `date`/`datetime` (un `DateTime` più ricco). |
| **Backed enum** | Un enum PHP i cui case mappano a un valore scalare (`case SCHEDULED = 'scheduled';`). Memorizzato come quella stringa nel DB. |
| **Attributo** | La sintassi di annotazione `#[...]` di PHP usata per i metadati (`#[ObservedBy]`, `#[Scope]`, `#[ScopedBy]`). |
| **Promozione delle proprietà nel costruttore** | Scorciatoia di PHP 8 che dichiara + assegna una proprietà nella firma del costruttore (`public function __construct(public readonly MatchRecord $matchRecord)`). |
| **`__()`** | L'helper di traduzione; cerca una chiave in `lang/*.json` per il locale attivo. |
| **`abort_if()`** | Lancia un'eccezione HTTP (con codice di stato) quando una condizione è vera — usato nelle guardie di cancellazione. |
| **UUID** | Un identificatore casuale a 128 bit usato come ogni chiave primaria/esterna qui, invece di interi auto-incrementanti. |

## Termini del dominio sport da combattimento

| Termine | Significato in linguaggio semplice |
|---------|-------------------------------------|
| **Tournament (Torneo)** | Un evento di sport da combattimento che un organizzatore gestisce; attraversa un ciclo di vita di stati. |
| **Athlete (Atleta)** | Un competitore, identificato pubblicamente dal *codice fiscale*. |
| **Codice fiscale (`tax_number`)** | Un identificativo fiscale nazionale usato come chiave di ricerca pubblica per un atleta. |
| **Discipline (Disciplina)** | Lo stile / regolamento di combattimento di un incontro (es. un formato di kickboxing), che porta la configurazione di round di default. |
| **Weight category (Categoria di peso)** | Una fascia di peso (con un limite numerico) entro cui gli atleti competono. |
| **Registration (Iscrizione)** | L'iscrizione di un atleta a uno specifico torneo, in una disciplina + categoria di peso scelte; traccia pagamento, arrivo, peso. |
| **Peso (`weight_in`)** | Il peso misurato dell'atleta il giorno della gara, confrontato con il limite di categoria. |
| **Match record / incontro** | Un singolo combattimento tra due atleti sulla card. |
| **Fight card (Card degli incontri)** | L'elenco ordinato degli incontri in un torneo (la colonna `sort` dà l'ordine). |
| **Angolo rosso / angolo blu** | I due atleti avversari in un incontro (denominazione standard degli sport da combattimento per i due lati). |
| **Round** | Un segmento a tempo di un incontro; un incontro ha `rounds` round di `minutes_per_round` ciascuno. |
| **Punti dei giudici (`judges_points`)** | Punteggi per round e per giudice, memorizzati come JSON in `judges_points`. |
| **End method (Metodo di conclusione)** | Come è finito un incontro: decisione (unanime/divisa), KO, TKO, squalifica, pareggio o no contest. |
| **KO / TKO** | Knockout / knockout tecnico — modi in cui un incontro finisce prima dei cartellini. |
| **Winner (Vincitore)** | L'atleta dichiarato vincitore; nullable (un pareggio o no-contest non ne ha). |
| **Forced (Forzato)** | Un flag su un incontro che indica che è stato abbinato/creato con override invece dell'abbinamento normale. |
