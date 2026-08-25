# Produzione e staging sullo stesso VPS

L'immagine `matches-api` (`Dockerfile`) è la stessa per tutti gli ambienti: **nessun `.env` è incluso nell'immagine**,
ogni impostazione arriva come variabile d'ambiente al deploy (`entrypoint.sh` fa `artisan optimize` + `migrate --force`
al boot).

Questo documento descrive come far convivere **produzione e staging su un unico VPS**
condividendo i container `postgres` e `redis`. Per il dimensionamento e i costi vedi
[`docs/vps_costs/README.md`](../../docs/vps_costs/README.md) — l'opzione qui descritta è la §6
"Opzione A", che ha un costo incrementale di circa **€0,10/anno**.

---

## 1. Cosa si condivide e cosa si duplica

| Componente          | Scelta              | Note                                       |
|---------------------|---------------------|--------------------------------------------|
| `postgres`          | **condiviso**       | un secondo database, stesse credenziali    |
| `redis`             | **condiviso**       | indici DB e prefissi diversi               |
| reverse proxy       | **condiviso**       | due hostname in più                        |
| `matches-api`       | **duplicato**       | `matches-api-staging`, limiti ridotti (§6) |
| `matches-dashboard` | **duplicato**       | `matches-dashboard-staging`                |
| Object Storage      | **bucket separati** | backup e media, vedi §4                    |

---

## 2. PostgreSQL — isolamento reale

I database Postgres hanno un nome, quindi bastano due database sulla stessa istanza con le stesse credenziali:

```dotenv
# produzione
DB_DATABASE=matches_api

# staging
DB_DATABASE=matches_api_staging
```

Nessun caveat: schema, dati e migrazioni sono completamente separati.

### Come si crea il secondo database

`POSTGRES_DB` crea un solo database. Il secondo si crea con uno script in
`/docker-entrypoint-initdb.d/`, lo stesso meccanismo che Sail usa già per il database di test
(`docker/pgsql/create-testing-database.sql`, montato in `compose.yaml`). Lo script vive in
`docker/staging/` perché è staging a possederlo, anche se è il container Postgres di produzione a
eseguirlo:

```yaml
postgres:
    image: 'postgres:17-alpine'
    environment:
        POSTGRES_DB: matches_api
        POSTGRES_USER: '${DB_USERNAME}'
        POSTGRES_PASSWORD: '${DB_PASSWORD}'
    volumes:
        - 'pgdata:/var/lib/postgresql/data'
        - './docker/staging/create-staging-database.sql:/docker-entrypoint-initdb.d/10-create-staging-database.sql:ro'
```

Lo script è idempotente (`WHERE NOT EXISTS ... \gexec`), quindi non fallisce se il database
esiste già.

### Autorizzazioni: non serve nessun GRANT

L'entrypoint di Postgres esegue gli script di init **come `POSTGRES_USER`**, quindi quell'utente
è il `OWNER` del database creato e ha già tutti i privilegi — schema `public` incluso, che da
Postgres 15 è ristretto ai soli owner. Le stesse credenziali funzionano su entrambi i database
senza aggiungere una riga.

Se in futuro vuoi credenziali separate, allora sì servono i privilegi espliciti:

```sql
CREATE USER matches_staging WITH PASSWORD '...';
ALTER DATABASE matches_api_staging OWNER TO matches_staging;
```

### Se il volume esiste già

Gli script in `/docker-entrypoint-initdb.d/` girano **solo al primo avvio**, quando `PGDATA` è
vuoto. Su un volume già inizializzato il database va creato a mano, una volta:

```bash
docker compose exec postgres createdb -U "$DB_USERNAME" -O "$DB_USERNAME" matches_api_staging
```

Poi `docker compose exec matches-api-staging php artisan migrate --force` (o lascialo fare a
`entrypoint.sh` al boot).

---

## 3. Redis — isolamento logico

**I database Redis non hanno un nome: sono numerati da 0 a 15.** Non esistono un DB
`production` e uno `staging`; si separa per indice. Container, host, porta e password restano gli stessi.

```dotenv
# produzione
REDIS_DB=0
REDIS_CACHE_DB=1

# staging
REDIS_DB=2
REDIS_CACHE_DB=3
```

Sopra all'indice ci sono due prefissi di chiave, entrambi derivati da `APP_NAME`:

- `REDIS_PREFIX` — `config/database.php`, default `Str::slug(APP_NAME).'-database-'`
- `HORIZON_PREFIX` — `config/horizon.php`, default `Str::slug(APP_NAME,'_').'_horizon:'`

Cambiando `APP_NAME` divergono da soli, ma conviene renderli espliciti per non dipendere da quel default:

```dotenv
# staging
REDIS_PREFIX=matches_staging-database-
HORIZON_PREFIX=matches_staging_horizon:
```

**Se i due ambienti condividono `HORIZON_PREFIX`, ciascuna dashboard Horizon elenca anche i job dell'altro.**

---

## 4. Variabili che NON vanno mai condivise

Anche quando il servizio sottostante è condiviso, questi valori devono essere distinti:

| Variabile                                         | Perché                                                                  |
|---------------------------------------------------|-------------------------------------------------------------------------|
| `APP_KEY`                                         | stessa chiave ⇒ i token e i cookie di staging sono validi in produzione |
| `APP_NAME`                                        | è ciò che fa divergere i prefissi Redis di default                      |
| `APP_URL`                                         | usato nei link delle mail (reset password)                              |
| `AWS_BUCKET` (backup) e bucket media              | un `migrate:fresh` di staging altrimenti tocca lo storage di produzione |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `..._SECRET` | credenziali websocket distinte                                          |
| `HORIZON_USERNAME` / `HORIZON_PASSWORD`           | Basic Auth della dashboard (`HorizonBasicAuth`)                         |
| `LOG_VIEWER_*`                                    | idem per `/log-viewer`                                                  |

`APP_DEBUG=false` anche in staging, se l'ambiente è raggiungibile da internet.

---

## 5. Rete e reverse proxy

Le porte *dentro* al container non cambiano (`:80` nginx, `REVERB_SERVER_PORT` per Reverb):
sono container distinti, quindi non collidono. Deve differire solo la **porta pubblicata sull'host** e la mappatura per
hostname nel proxy:

```
api.<domain>              -> matches-api:80
reverb.<domain>           -> matches-api:8080
dashboard.<domain>        -> matches-dashboard:8080

staging-api.<domain>      -> matches-api-staging:80
staging-reverb.<domain>   -> matches-api-staging:8080
staging.<domain>          -> matches-dashboard-staging:8080
```

Ricorda che `REVERB_SERVER_PORT` è la porta di **ascolto**, mentre `REVERB_HOST` / `REVERB_PORT`
/ `REVERB_SCHEME` sono ciò che viene comunicato ai client — vedi il commento in
`supervisord.conf`. In staging `REVERB_HOST=staging-reverb.<domain>`.

---

## 6. Limiti di risorse per lo staging

Staging deve stare nel margine lasciato dalla produzione. Riduci i due pool che dominano il worst case:

| Impostazione                                 | Produzione | Staging |
|----------------------------------------------|------------|---------|
| `php-fpm.conf` → `pm.max_children`           | 8          | **2**   |
| `config/horizon.php` (prod) → `maxProcesses` | 10         | **2**   |

Con questi valori lo staging pesa ~0,4 GB idle e ~1,2 GB di picco, e il totale dei due ambienti resta intorno ai 5 GB su
8 (VPS-2).

Imposta anche `--maxmemory 256mb --maxmemory-policy allkeys-lru` sul container Redis: senza un tetto è l'unico processo
dello stack che può crescere senza limite.

---

## 7. Cosa resta NON isolato

Due punti che l'opzione "container condivisi" non risolve:

1. **Redis è un'unica istanza.** `redis-cli FLUSHALL` cancella entrambi gli ambienti (`FLUSHDB`
   no, agisce sull'indice corrente), e l'eviction `allkeys-lru` è per-istanza: un burst di cache in staging può
   sfrattare chiavi di produzione. Rimedio, se serve: un secondo container Redis,
   ~15 MB di RAM.
2. **Postgres è un unico processo.** Una query pesante in staging consuma CPU e `shared_buffers`
   della produzione.

**Regola operativa: non far girare carico su staging durante un torneo live.** È l'unico scenario in cui questi due
limiti diventano un problema reale — ed è anche il motivo per cui esiste l'alternativa a VPS separati (§6 di
`docs/vps_costs/README.md`).

Nota su Reverb: il pub/sub Redis **ignora l'indice del database**. Oggi non è un problema perché
`REVERB_SCALING_ENABLED` è `false` (`config/reverb.php`), ma se lo attivi devi dare a staging un
`REVERB_SCALING_CHANNEL` diverso, altrimenti i due server Reverb si scambiano gli eventi.

---

## 8. Build e deploy

- **Non buildare sul VPS.** `pnpm generate` della dashboard richiede
  `NODE_OPTIONS=--max-old-space-size=4096`: con due ambienti sulla stessa macchina questo passa da consiglio a vincolo.
  Builda in CI e fai `docker pull`.
- `entrypoint.sh` esegue `migrate --force` a ogni boot: il deploy ha downtime, per entrambi gli ambienti. Puoi
  disattivarlo con `RUN_MIGRATIONS=false` se preferisci migrare a mano.
- Aggiorna prima staging, verifica, poi produzione. Sono la stessa immagine con tag diversi.

---

## 9. Accesso alle UI di amministrazione

Horizon, log-viewer e Arcane **non sono raggiungibili da internet**. L'unico utente è l'operatore, e per un utente
singolo configurare OAuth è complessità senza guadagno: si arrivano con un `LocalForward` SSH, quindi
l'autenticazione è la chiave SSH — più forte della basic auth che protegge oggi quei path.

### La porta admin del container

`nginx.conf` definisce due server block sulla stessa applicazione:

| Porta interna | Chi ci arriva              | `/horizon`, `/log-viewer` |
|---------------|----------------------------|---------------------------|
| `:80`         | il reverse proxy, pubblico | **404**                   |
| `:8081`       | solo il tunnel SSH         | serviti                   |

Le location comuni stanno in `nginx-app.conf`, incluso da entrambi: una divergenza fra due copie duplicate sarebbe un
buco di sicurezza silenzioso.

**Il filtro non può stare in PHP.** `bootstrap/app.php` ha `trustProxies(at: '*')`, quindi `$request->ip()` viene letto
da `X-Forwarded-For` ed è falsificabile con un header; e comunque nginx nel container vede sempre l'IP del reverse
proxy, identico per traffico pubblico e tunnelato. **L'IP non discrimina, la porta di ingresso sì.**
`HorizonBasicAuth` e `LogViewerBasicAuth` restano al loro posto come seconda cintura.

### Pubblicazione: solo su loopback

La porta interna è `:8081` per entrambi gli ambienti (sono container distinti, non collidono — vedi §5). Cambia solo la
porta sull'host:

```yaml
# produzione
ports:
  - '127.0.0.1:8081:8081'
# staging
ports:
  - '127.0.0.1:8082:8081'
```

**Il `127.0.0.1` non è cosmetico.** `- '8081:8081'` pubblica su `0.0.0.0`, e Docker scrive le sue regole in
`DOCKER-USER`/nat **scavalcando le INPUT di ufw**: la porta sarebbe raggiungibile da internet con il firewall
apparentemente chiuso. `EXPOSE` nel Dockerfile è solo documentazione e non pubblica nulla: il binding è l'unico
controllo reale.

### Arcane

Stesso schema. `docker.sock` montato significa root-equivalente sul VPS, quindi non esiste una configurazione in cui
valga la pena esporlo:

```yaml
services:
  arcane:
    image: ghcr.io/getarcaneapp/manager:latest
    ports:
      - '127.0.0.1:3552:3552'
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - arcane-data:/app/data
    environment:
      - APP_URL=http://localhost:3552
      - ENCRYPTION_KEY=<32 byte random>
      - JWT_SECRET=<32 byte random>
    cgroup: host
    mem_limit: 256m
    restart: unless-stopped
```

- `APP_URL` deve essere l'URL con cui lo raggiungi davvero, altrimenti redirect e cookie di sessione si rompono.
- `ENCRYPTION_KEY` e `JWT_SECRET` servono comunque, anche in tunnel: cifrano a riposo le credenziali che Arcane salva
  nel suo SQLite (env var dei progetti, credenziali registry). Generali random, non lasciare i placeholder della doc.
- `mem_limit`: §2.2 di `docs/vps_costs/README.md` nota che Redis è l'unico processo dello stack senza tetto. Arcane non
  deve diventare il secondo.
- Niente TLS: SSH cifra già, e su `http://` esplicito non servono cookie `secure`.

### Il tunnel

```sshconfig
# ~/.ssh/config
Host matches-vps
  HostName <ip-vps>
  User deploy
  LocalForward 8081 127.0.0.1:8081   # horizon + log-viewer, produzione
  LocalForward 8082 127.0.0.1:8082   # horizon + log-viewer, staging
  LocalForward 3552 127.0.0.1:3552   # arcane
```

`ssh -N matches-vps`, poi `http://localhost:8081/horizon`, `http://localhost:8081/log-viewer`,
`http://localhost:3552`.

**Perché la porta locale del `LocalForward` è libera.** Horizon inlinea CSS e JS nel layout, ma log-viewer li serve da
`public/vendor/log-viewer/` con `asset()`, e `trustProxies(at: '*')` fa ignorare a Symfony la porta dell'header `Host`
in favore di `X-Forwarded-Host`/`X-Forwarded-Port`. Sulla porta admin non c'è nessun proxy che li mandi, quindi
`asset()` genererebbe URL **senza porta** e la pagina resterebbe senza CSS. Per questo il server block `:8081` fa
`set $forwarded_host $http_host;` e `nginx-app.conf` lo passa a php-fpm con `if_not_empty`: sulla `:80` la variabile è
vuota, il parametro viene saltato e gli `X-Forwarded-*` del reverse proxy passano intatti.

Gli asset di log-viewer sono generati al build (`vendor:publish --tag=log-viewer-assets` nel Dockerfile): non sono nel
repo e `composer.json` non li pubblica, quindi in locale con Sail `/log-viewer` è senza CSS finché non lanci
`vendor/bin/sail artisan log-viewer:publish`.

### Il ruolo di Arcane, e cosa non deve diventare

**Compose in git, build in CI, Arcane fa solo log, restart, stats ed exec.** Arcane sa tenere i progetti Compose nel
suo volume dati, ma quel file non è versionato: se diventa lui la fonte di verità della configurazione, il primo drift
lo scopri durante un torneo. E non usare il suo volume `/builds`: buildare sul VPS contraddice §8.

Arcane non risolve nessuno dei limiti di `docs/vps_costs/README.md` §7 — l'alta disponibilità resta zero, il deploy ha
ancora downtime, il restore resta non testato.
