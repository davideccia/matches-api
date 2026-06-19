# 09 — Configurazione e Ambiente

> Vedi anche: [03 — Build, Avvio e Test](03-build-run-test.md), [08 — Real-time, File e PDF](08-realtime-files-and-pdf.md)

Questo capitolo spiega come è configurata l'app: la convenzione variabile-d'ambiente → file-di-config,
le scelte di driver che rendono semplice lo sviluppo locale e le manopole di sicurezza (locale,
throttling, auth di Horizon).

## Come funziona la configurazione in Laravel

La configurazione vive in `config/*.php`, un file per sottosistema. Ogni file legge i valori dalle
**variabili d'ambiente** via `env('CHIAVE', 'default')`, e il codice legge il valore risolto via
`config('file.chiave')`. La regola d'oro: **il codice applicativo chiama `config()`, mai `env()`
direttamente** — perché la config può essere messa in cache in produzione (`config:cache`), dopodiché
`env()` restituisce null.

Ispeziona qualsiasi valore con:

```bash
vendor/bin/sail artisan config:show database.default
```

Il template locale è `.env.example`; copialo in `.env` al primo setup.

## Scelte di driver (il default "database per tutto")

La decisione che definisce lo sviluppo locale: quasi ogni sottosistema collegabile usa di default un
driver **basato su database**, così puoi avviare l'intera app con nient'altro che SQLite — niente Redis,
nessun servizio esterno.

| Sottosistema | File di config | Driver di default |
|--------------|----------------|-------------------|
| Database | `config/database.php` | `sqlite` |
| Cache | `config/cache.php` | `database` |
| Coda | `config/queue.php` | `database` |
| Sessione | `config/session.php` | `database` |
| Broadcasting | `config/broadcasting.php` | `null` (spento salvo configurazione) |

Il broadcasting è di default `null` (no-op): il push real-time è opt-in. Per esercitare i WebSocket in
locale imposti `BROADCAST_CONNECTION=reverb` ed esegui il server Reverb + un worker della coda (vedi
[Capitolo 03](03-build-run-test.md) e [Capitolo 08](08-realtime-files-and-pdf.md)). In produzione la
coda è pensata per girare su Redis sotto Horizon.

## Impostazioni applicative notevoli

### URL del frontend

L'API è consumata da una SPA separata. La sua URL di base è configurata e usata nel costruire i link di
reset password:

```php
// config/app.php
'frontend_url' => env('APP_FRONTEND_URL', 'http://localhost:3000'),
```

```php
// app/Models/User.php, righe 36-40
public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
{
    $url = config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($this->email);
    $this->notify(new ResetPasswordNotification($url));
}
```

### Locale

Il locale di default e il fallback sono `en`; l'insieme supportato è `['en', 'it']`. Il middleware
`SetLocale` (vedi [Capitolo 04](04-request-lifecycle.md)) sovrascrive il default per ogni richiesta
dall'header `Accept-Language`. Le stringhe di traduzione vivono in `lang/en.json` e `lang/it.json`; i
metodi `label()` degli enum e i messaggi d'errore degli observer (`__('errors...')`) si risolvono contro
di esse.

```php
// config/app.php
'locale' => env('APP_LOCALE', 'en'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
```

### CORS

Poiché una SPA di origine separata chiama questa API, `config/cors.php` controlla quali
origini/header sono permessi. Configuralo per corrispondere all'origine del frontend in deployment.

## Manopole di sicurezza e rate-limiting

- **Sanctum** (`config/sanctum.php`) — il guard a token API per la superficie admin. Il login emette un
  token (`createToken('api')`); il gruppo middleware `auth:sanctum` protegge ogni rotta admin tranne
  login/reset-password.
- **Throttling** — l'intera superficie pubblica è avvolta in `throttle:10,1` (10 req/min) in
  `bootstrap/app.php`; l'endpoint admin `auth/user` aggiunge il proprio `throttle:10,1`.
- **Alias middleware `ability`** — `bootstrap/app.php` fa l'alias di `ability` su `CheckForAnyAbility`
  di Sanctum, disponibile per i controlli di ability dei token dove servono.
- **Auth della dashboard di Horizon** — il middleware `HorizonBasicAuth` + `HorizonServiceProvider`
  proteggono l'accesso alla dashboard della coda. Il tuning di Horizon (numero di worker, bilanciamento)
  è in `config/horizon.php`.

## Fidarsi dei proxy

`bootstrap/app.php` imposta `$middleware->trustProxies(at: '*')`, così l'app onora gli header
`X-Forwarded-*` — necessario dietro un load balancer / reverse proxy affinché gli URL generati e gli IP
dei client siano corretti. Restringi l'elenco `at:` al range del tuo proxy per l'hardening in produzione.
