# Security Issues — stato e piano

Audit della codebase eseguito il **2026-08-27** su `main` (`d1a0fad`).

Questo documento traccia i progressi: ogni voce ha uno stato, e le decisioni prese (incluse quelle di **non**
intervenire) restano registrate qui.

**Legenda stato:** 🔲 Aperto · 🔄 In corso · ✅ Risolto · ⛔️ Non si interviene (decisione presa)

---

## Indice

| #  | Problema                                        | Gravità | Stato                        |
|----|-------------------------------------------------|---------|------------------------------|
| 1  | CORS wildcard con credenziali                   | Alta    | ⛔️ Non si interviene         |
| 2  | Credenziali di default Horizon / Log Viewer     | Alta    | ⛔️ Non si interviene         |
| 3  | `trustProxies(at: '*')` → rate limit aggirabile | Alta    | ⛔️ Non si interviene         |
| 4  | Disclosure PII pubblica tramite codice fiscale  | Media   | ✅ Risolto (2026-08-27)      |
| 5  | IDOR sul PDF pubblico della registrazione       | Media   | ✅ Risolto (2026-08-27)      |
| 6  | Resource admin usata su endpoint pubblico       | Media   | ✅ Risolto (2026-08-27)      |
| 7  | Nessuna autorizzazione oltre `users`            | Media   | 🔲 Aperto                    |
| 8  | Token Sanctum senza abilities                   | Media   | 🔲 Aperto                    |
| 9  | Confronto credenziali timing-unsafe             | Media   | 🔲 Aperto                    |
| 10 | Abuso del form pubblico (creazione illimitata)  | Media   | ✅ Risolto (2026-08-27)      |
| 11 | Password deboli ammesse                         | Bassa   | 🔲 Aperto                    |
| 12 | Nessun middleware security headers              | Bassa   | 🔲 Aperto                    |
| 13 | Login throttled solo per IP                     | Bassa   | ✅ Risolto (2026-08-28)      |
| 14 | Upload temporanei: cache key non namespaced     | Bassa   | ✅ Risolto (2026-08-28)      |
| 15 | `composer audit` assente dalla CI               | Bassa   | ✅ Risolto (2026-08-28)      |

---

## 1. CORS wildcard con credenziali — ⛔️ Non si interviene

**File:** `config/cors.php:19,29` · `.env.example`

```php
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
'supports_credentials' => true,  // era false
```

`.env.example` distribuisce `CORS_ALLOWED_ORIGINS=*`. Wildcard più credenziali permette a qualsiasi origine di chiamare
l'API.

> **Decisione (2026-08-27):** non si interviene per ora.

---

## 2. Credenziali di default Horizon / Log Viewer — ⛔️ Non si interviene

**File:** `.env.example` · `config/log-viewer.php:27`

`HORIZON_USERNAME=admin` / `HORIZON_PASSWORD=12345678` e le stesse per `LOG_VIEWER_*`. Sono i pannelli esposti su
`api.<domain>/horizon` e `/log-viewer`: un deploy che copia l'example espone code, payload dei job e log applicativi.
Inoltre
`'require_auth_in_production' => false`.

> **Decisione (2026-08-27):** non si interviene per ora.

---

## 3. `trustProxies(at: '*')` — ⛔️ Non si interviene

**File:** `bootstrap/app.php:36`

Fidandosi di qualsiasi proxy, `X-Forwarded-For` è spoofabile dal client: **tutti i rate limit diventano aggirabili**
(`throttle:10,1` sul pubblico, `throttle:5,1` su login e reset password), perché sono chiavati sull'IP.

> **Decisione (2026-08-27):** non si interviene per ora.
> **⚠️ Da tenere presente:** finché resta aperto, ogni mitigazione basata su throttle per IP va considerata un
> deterrente, non un controllo affidabile. È il motivo per cui il limite anti-abuso introdotto nel #4 è chiavato sul
> codice fiscale e non sull'IP.

---

## 4. Disclosure PII pubblica tramite codice fiscale — ✅ Risolto

### Il problema

`GET registration_form/athletes/{tax_number}` restituiva nome, cognome, data di nascita, genere e team senza
autenticazione. Il codice fiscale italiano è **deterministicamente calcolabile** da nome, cognome, data e luogo di
nascita: non è un segreto, quindi non funziona come prova di identità.

Esistevano **tre** vie per sfruttarlo, non una:

1. la GET di lookup;
2. `POST registration_form/athletes`, che con `firstOrCreate` sul `tax_number` **restituiva il record esistente**;
3. la catena `POST registrations` → `GET registrations/{id}/pdf`: il PDF stampa `athlete->full_name` e
   `storeRegistration` accettava un `athlete_id` senza prova di identità.

In più ogni scrittura pubblica era anonima: chiunque poteva creare anagrafiche e iscrizioni fantasma in massa.

### La soluzione adottata

Due controlli distinti, ciascuno per il problema che gli compete.

**Lettura — CF + email.** Il prefill vive su `POST registration_form/athletes/lookup` con `{ tax_number, email }`.
L'email registrata è il secondo fattore, non derivabile dal CF. Il confronto passa da
`Athlete::emailMatches()` (`hash_equals`, a tempo costante) su valori canonicalizzati da
`Athlete::normalizeTaxNumber()` / `normalizeEmail()`, gli stessi usati da `AthleteObserver::saving`.

**Scrittura — codice OTP via email.** `POST registration_form/verification_code` emette un codice numerico a 6 cifre
(TTL 10 minuti, monouso), gestito da `app/Support/RegistrationVerificationCode.php`. `storeRegistration` è ora l'unico
punto di scrittura pubblica: verifica il codice e poi crea atleta **e** iscrizione in un'unica transazione. La rotta
`POST athletes` è stata eliminata.

**L'invariante che regge tutto:** se l'atleta esiste, il codice va **all'email registrata a DB**, mai a quella inviata
nella richiesta. Senza questa regola chiunque conosca un CF se lo farebbe recapitare da solo. È coperta dal test
`test_verification_code_for_existing_athlete_goes_to_the_stored_email` e verificata a mano su Mailpit.

### Difese di contorno

- Ogni fallimento risponde **400** con messaggio generico e identico (`errors.athlete_lookup_failed`): mai 404, che
  significherebbe "record assente" — l'informazione che non vogliamo dare.
- Il codice si **invalida dopo 5 tentativi errati** (6 cifre sono solo 10⁶ combinazioni) e un tentativo sbagliato non
  rinnova il TTL.
- `verification_code` risponde **sempre 204**, atleta esistente o no, email combaciante o no.
- Massimo **3 richieste di codice per CF ogni 15 minuti** (`RateLimiter`), per non trasformare l'endpoint in un vettore
  di mail bombing. Il limite è per codice fiscale e **non per IP**, proprio perché il #3 resta aperto.
- Throttle di rotta tramite **limiter con nome** (`public-athlete-lookup`, `public-verification-code`, definiti in
  `AppServiceProvider::boot`). ⚠️ Un `throttle:5,1` anonimo **non** funziona qui: `ThrottleRequests` chiava su
  `dominio|IP` e non sull'URI, quindi un secondo throttle anonimo condivide — e incrementa due volte — il contatore del
  `throttle:10,1` di gruppo, facendo scattare un 429 sulla prima richiesta a causa di traffico pubblico non correlato.
  Regressione coperta da `test_verification_code_is_not_throttled_by_unrelated_public_traffic`.
- CF ed email viaggiano nel **body** e non più nel path: non finiscono negli access log di nginx, nei log del reverse
  proxy, nell'header `Referer` né in cache intermedie. Per lo stesso motivo i `Log::warning` sui fallimenti registrano
  **solo l'IP**.

### ⚠️ Limite noto — rischio accettato

**L'oracolo di esistenza si riduce ma non sparisce.** Chi sonda con un CF e un'email falsa non riceve mai il codice e
non ottiene **nessun dato personale**, ma può ancora dedurre *se* un CF è presente dal fatto che il codice arrivi o meno
alla propria casella. Costo per tentativo: una mailbox reale, un'email tracciata e i limiti qui sopra.

Chiuderlo del tutto richiederebbe un flusso di verifica ulteriore, fuori portata nel lungo periodo. **Registrato come
rischio accettato, non come lavoro futuro pianificato.**

### File toccati

`database/migrations/2026_06_15_100003_create_athletes_table.php` (colonna `email`, NOT NULL, non unique) ·
`app/Models/Athlete.php` · `app/Observers/AthleteObserver.php` · `app/Support/RegistrationVerificationCode.php` ·
`app/Notifications/RegistrationVerificationCodeNotification.php` ·
`resources/views/emails/registration/verification-code.blade.php` ·
`app/Http/Controllers/Api/PublicRegistrationFormController.php` · `routes/api.public.php` ·
`app/Http/Requests/PublicRegistrationForm/*` · `app/Http/Resources/Public/PublicRegistrationResource.php` ·
`lang/en.json` · `lang/it.json` · `database/factories/AthleteFactory.php` · `database/seeders/AthleteSeeder.php`

Rimossi: `PublicRegistrationFormShowRequest`, `PublicRegistrationFormStoreRequest`, i metodi `showAthlete` e
`storeAthlete`, le rotte `GET athletes/{tax_number}` e `POST athletes`.

---

## 5. IDOR sul PDF pubblico della registrazione — ✅ Risolto

**File:** `routes/api.public.php` · `app/Http/Resources/Public/PublicRegistrationResource.php`

`GET registration_form/registrations/{registration}/pdf` non aveva autenticazione: chiunque conoscesse lo UUID
scaricava il PDF con tutta la PII dell'atleta. `HasUuids` genera UUID **ordinati** (prefisso temporale), quindi
l'entropia è inferiore a un UUID v4 puro.

**Risolto insieme al #4.** La rotta è ora protetta dal middleware `signed` e ha un nome
(`public.registration_form.registrations.pdf`). Il link viene generato con `URL::temporarySignedRoute()` (scadenza 60
minuti) e restituito come `pdf_url` da `storeRegistration`, cioè solo a chi ha appena completato l'iscrizione con un
codice verificato. La rotta admin resta invariata dietro `auth:sanctum`.

Coperto da `test_registration_pdf_requires_a_valid_signature` e
`test_registration_pdf_is_reachable_through_the_signed_url_returned_on_store`.

---

## 6. Resource admin usata su endpoint pubblico — ✅ Risolto

**File:** `app/Http/Resources/Public/PublicRegistrationResource.php`

`storeRegistration` restituiva `RegistrationResource`, un `parent::toArray()` nudo che esponeva `paid_at`, `arrived`,
`weight_in` e `notes` a un chiamante non autenticato.

**Risolto insieme al #4** con `Public\PublicRegistrationResource`, whitelist esplicita di `id`, label di
torneo/disciplina/categoria e `pdf_url` — come già fatto per `PublicRegistrationAthleteResource`. Nemmeno
`athlete_id` viene più restituito.

Coperto da `test_store_registration_response_hides_internal_fields`.

---

## 7. Nessuna autorizzazione oltre `users` — 🔲 Aperto

Tutte le `FormRequest` diverse da `User*` fanno `authorize() => true`. Qualsiasi utente autenticato può leggere,
modificare ed eliminare ogni atleta, torneo, registrazione e match record, inclusi i `DELETE {resource}/bulk`.

Se tutti gli utenti sono staff fidato è una scelta legittima e consapevole — da annotare qui come tale. Altrimenti
servono policy per risorsa.

---

## 8. Token Sanctum senza abilities — 🔲 Aperto

**File:** `bootstrap/app.php:44` · `app/Http/Controllers/Api/AuthController.php:25`

L'alias `ability` (`CheckForAnyAbility`) è registrato ma non usato da nessuna rotta. Ogni token emesso vale per tutte le
operazioni, per una settimana.

---

## 9. Confronto credenziali timing-unsafe — 🔲 Aperto

**File:** `app/Http/Middleware/HorizonBasicAuth.php:22`

```php
if ($providedUser === $username && $providedPass === $password) {
```

**Proposta:** `hash_equals()` su entrambi i confronti.

---

## 10. Abuso del form pubblico — ✅ Risolto

`storeAthlete` e `storeRegistration` permettevano la creazione illimitata di atleti e l'iscrizione di *qualsiasi*
`athlete_id` a qualsiasi torneo aperto, protetti solo dal throttle globale per IP (spoofabile, vedi #3).

**Risolto insieme al #4.** `POST athletes` non esiste più e `storeRegistration` — unico punto di scrittura pubblica —
richiede un codice OTP recapitato via email. Nessuna riga raggiunge più il database senza una casella verificata
dietro, e il limite anti-abuso è per codice fiscale invece che per IP.

---

## 11. Password deboli ammesse — 🔲 Aperto

**File:** `app/Http/Requests/Auth/ResetPasswordRequest.php:20` · `app/Http/Requests/User/UserStoreRequest.php:25`

`Password::min(8)` senza `->uncompromised()` né requisiti di complessità.

---

## 12. Nessun middleware security headers — 🔲 Aperto

Mancano `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`. Poco rilevante per le risposte JSON, ma Horizon,
Log Viewer e i PDF servono HTML.

---

## 13. Login throttled solo per IP — ✅ Risolto

**File:** `routes/api.admin.php:19` · `app/Providers/AppServiceProvider.php`

`throttle:5,1` era per IP e condiviso fra login, forgot e reset password. Nessun lockout per singolo account: un
attaccante con IP a rotazione (banale visto il #3) provava password illimitate su una singola casella.

**Soluzione.** Due limiter con nome, `auth-login` (login) e `auth-password-reset` (forgot + reset), ciascuno con **due**
`Limit`: 5/min per IP **e** 5/min per email normalizzata. Il secondo è il lockout per account: vale anche se l'IP
cambia a ogni tentativo.

Separare i due nomi evita inoltre che i tentativi di login consumino il contatore del reset password — lo stesso
inciampo documentato nel #4: un `throttle:5,1` anonimo chiava su `dominio|IP`, quindi rotte diverse condividono un
unico contatore.

Coperto da `test_login_is_locked_out_per_account_across_different_ips` e
`test_login_attempts_do_not_throttle_password_reset_requests`.

> **Limite noto:** il lockout per email è un vettore di DoS mirato (chi conosce l'indirizzo può tenerlo bloccato a
> 5 richieste/minuto). Con una finestra di un minuto il costo è accettato; se diventasse un problema, la via è un
> lockout progressivo invece di uno fisso.

---

## 14. Upload temporanei: cache key non namespaced — ✅ Risolto

**File:** `app/Support/TemporaryFile.php` · `app/Actions/StoreTemporaryUploadAction.php` ·
`app/Rules/TemporaryFileRule.php` · `app/Traits/InteractsWithMedia.php`

La chiave di cache era uno UUID nudo nel namespace condiviso, e la rule faceva `Cache::get($inputUtente)`: un utente
autenticato poteva consumare il file caricato da un altro, e un input arbitrario leggeva qualsiasi chiave di cache
dell'applicazione (con `RegistrationVerificationCode` nello stesso namespace).

**Soluzione.** La chiave diventa `tmp_upload:{userId}:{id}` ed è costruita in **un solo punto**,
`TemporaryFile::cacheKey()`. Scrittura e lettura passano da `TemporaryFile::put()` / `TemporaryFile::find()`, usate da
tutti e tre i chiamanti (action, rule, trait): nessun chiamante può dimenticarsi lo scoping, e `find()` verifica anche
il tipo dell'oggetto in cache.

Coperto da `test_temporary_upload_cannot_be_consumed_by_another_user` e dagli assert di
`test_store_uploads_file_and_caches_temporary_file` sulla forma della chiave.

---

## 15. `composer audit` assente dalla CI — ✅ Risolto

**File:** `.forgejo/workflows/docker-publish.yml` · `.forgejo/workflows/ghcr-publish.yml`

Le pipeline eseguivano `composer install` e poi buildavano e pubblicavano l'immagine: una CVE pubblicata su un pacchetto
in `composer.lock` finiva in produzione senza che nulla lo segnalasse.

**Soluzione.** Step `Security audit` (`composer audit --no-dev`) subito dopo `Install dependencies` in entrambi i
workflow. `composer audit` confronta le versioni esatte di `composer.lock` — dipendenze transitive incluse — con il
database advisory di Packagist ed esce con codice ≠ 0 se trova qualcosa, bloccando la build prima del push
dell'immagine. `--no-dev` limita il controllo a ciò che viene effettivamente spedito.

> **Limite noto:** l'audit gira solo sui push. Il lock non cambia, ma gli advisory sì: una CVE pubblicata dopo l'ultima
> build non viene rilevata finché non si builda di nuovo. Se il progetto rallenta, la via è un workflow schedulato
> settimanale che esegua il solo audit.

---

## Verifiche superate ✅

Cose già corrette, da non rimettere in discussione:

- **Nessuna SQL injection.** Gli `whereRaw` in `Athlete.php:143,149` sono parametrizzati; quello in
  `Registration.php:94` interpola solo un operatore derivato da un booleano.
- **Nessun `$guarded = []`** su alcun model.
- **Password con cast `hashed`** e `#[Hidden(['password', 'remember_token'])]` su `User`.
- **Nessuna user enumeration** su login (messaggio generico) né su forgot password (risposta sempre uguale).
- **Nessun `{!! !!}`** nelle view del progetto (solo nelle view vendor di medialibrary).
- **Escalation di privilegio bloccata:** in `UserStoreRequest` / `UserUpdateRequest` la regola
  `superadmin` esiste solo se il richiedente è già superadmin, quindi `validated()` la scarta per tutti gli altri.
