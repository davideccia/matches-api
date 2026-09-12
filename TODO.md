# TODO — privacy / GDPR (backend)

Estratto da `matches-dashboard/TODO.md` il 2026-09-12, durante la revisione
GDPR di `public/privacy.txt` (frontend) contro il comportamento reale del
codice. Le voci qui sotto sono cambiamenti di codice che riguardano
esclusivamente questo repository (Laravel API) — le voci di solo frontend, le
decisioni del Titolare e le informativa restano in `matches-dashboard/TODO.md`.

---

## 1. Region di hosting e backup — default fuorviante

Il Titolare ha confermato che tutte le risorse di produzione (host API +
database, bucket dei backup, provider SMTP) risiedono nel SEE (vedi
`matches-dashboard/TODO.md` §1.1). Resta però un default fuorviante nel
codice:

- `.env`/`.env.example` hanno `AWS_DEFAULT_REGION=us-east-1` come default e
  `BACKUP_AWS_DEFAULT_REGION` vuoto — statunitense per convenzione AWS, non
  perché la produzione giri lì. Correggere il default a una region UE (o
  lasciarlo vuoto e documentare che va sempre impostato esplicitamente), così
  un deploy futuro non lo eredita per errore.

## 2. Nessun job di retention — CRITICO per l'art. 5(1)(e)

`routes/console.php` schedula solo `CleanupTemporaryUploadsCommand`,
`horizon:snapshot` e i backup. Manca tutto il resto. Termini di conservazione
decisi dal Titolare (`matches-dashboard/TODO.md` §1.2):

| Dato                             | Termine                                                 |
| --------------------------------- | -------------------------------------------------------- |
| Dati atleta, iscrizioni e dati sportivi (incontri, esiti) | durata del rapporto + 5 anni dall'ultima partecipazione |
| Account staff                     | incarico + 12 mesi                                        |
| Log applicativi                   | 12 mesi                                                   |

Da implementare:

- purge/anonimizzazione degli atleti oltre il termine sopra;
- `sanctum:prune-expired` (vedi anche §3 qui sotto);
- rotazione dei log: `LOG_CHANNEL=stack` / `LOG_STACK=single` → file unico che
  cresce senza limiti. Passare a `daily` con `LOG_DAILY_DAYS` coerente con i
  12 mesi dichiarati;
- pulizia della tabella `sessions` e di `password_reset_tokens` scaduti.

Finché non esiste, l'informativa (punto 11) promette una cancellazione che
non avviene.

## 3. Token Sanctum senza scadenza lato server

`config/sanctum.php` → `'expiration' => null`: i token non scadono mai lato
server (scade solo il cookie che li porta, lato frontend, dopo 7 giorni).

Da fare: impostare `SANCTUM_EXPIRATION` (es. 10080 minuti = 7 giorni) e
schedulare `sanctum:prune-expired` (vedi anche il job di retention al §2).
Il frontend (`matches-dashboard`) ha un commento che descrive questo
allineamento come già esistente — è falso finché questo punto non è chiuso;
il fix di quel commento è tracciato separatamente in
`matches-dashboard/TODO.md`.

## 4. Procedura di esercizio dei diritti — comandi artisan

L'informativa promette accesso, cancellazione, portabilità, opposizione e
risposta entro un mese. Oggi non esiste nessuno strumento: la cancellazione
di un atleta va fatta a mano e non è chiaro cosa resti (`match_records` ha
`cascadeOnDelete` sugli angoli ma `nullOnDelete` sul vincitore; restano i
`red_corner_team`/`blue_corner_team` denormalizzati, i PDF già generati e i
backup per 16 giorni).

Serve almeno un comando artisan di export (portabilità) e uno di
cancellazione/anonimizzazione che copra media, note e storico. La procedura
scritta interna che accompagna questi comandi resta tracciata in
`matches-dashboard/TODO.md`.

## 5. Password in chiaro via email

`User::$generatedPassword` + `UserObserver` generano la password del nuovo
utente staff e la inviano via email in chiaro. Difficile difendere l'art. 32
con questo flusso: sostituire con un link firmato di impostazione password
(riusando il meccanismo di reset già presente).

## 6. CORS con wildcard e credenziali

`config/cors.php`: `allowed_origins` da `CORS_ALLOWED_ORIGINS` (default `*`)
con `supports_credentials => true`. Combinazione non valida e non sicura:
verificare che in produzione la variabile sia impostata sui domini reali e
cambiare il default in `.env.example` (oggi `CORS_ALLOWED_ORIGINS=*`).

## 7. Credenziali di default negli strumenti di amministrazione

`.env.example` distribuisce `HORIZON_USERNAME/PASSWORD=admin/12345678` e
`LOG_VIEWER_USERNAME/PASSWORD=admin/12345678`, con `LOG_VIEWER_ENABLED=true`.
Il log viewer espone log applicativi che possono contenere dati personali:
verificare che in produzione siano credenziali reali (o che sia disattivato).

## 8. Codice morto: upload fotografia atleta (nota, non urgente)

Il Titolare ha confermato che il caricamento della fotografia non è mai
raggiungibile da nessuna UI (né in `matches-dashboard`, né altrove): il
codice in `AthleteController.php:73-74, 99-100` che gestisce l'upload è
codice morto. `public/privacy.txt` è stato conseguentemente ripulito da ogni
riferimento alla fotografia (vedi `matches-dashboard/TODO.md` §2.2).

Da decidere: rimuovere questo codice morto, oppure tenerlo in vista di una
futura reintroduzione — in tal caso andrà aggiunto un tracciamento del
consenso (es. `photo_consent_at`) prima di riattivarlo, e ripristinato il
relativo paragrafo dell'informativa.

## 9. Esclusione dei dati sensibili dalla risorsa pubblica — CONFERMATO 2026-09-12

`public/privacy.txt` §8 dichiara che il tabellone pubblico
(`GET /api/public/tournaments/{tournament}/match_records`) **non** pubblica:
codice fiscale, data di nascita, email, numero di telefono, peso rilevato,
note e dati di pagamento.

Confermato dal Titolare: `registrations.notes` e `match_records.notes` non
vengono inclusi nella risposta di `PublicAthleteResource` /
`PublicMatchRecordResource` (vedi anche `matches-dashboard/TODO.md` §2.5 sul
rischio di dati sanitari in quei campi liberi). Nessun cambiamento di codice
necessario qui.

---

## Nota

Questa lista nasce da una verifica tecnica codice-vs-documento condotta sul
repository frontend (`matches-dashboard`), non da una validazione legale. Le
scelte di base giuridica e i termini di conservazione sono decisioni del
Titolare, non tecniche.
