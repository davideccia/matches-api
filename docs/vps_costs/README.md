# Costi VPS OVHcloud — stack Matches (API + Dashboard + Object Storage)

> Documento di stima. Prezzi OVHcloud rilevati il **2026-08-25** dalle pagine
> pubbliche `ovhcloud.com/it`, **IVA esclusa**, con **impegno 12 mesi**. I prezzi
> VPS sono cambiati il 2026-04-02 (+9~11%): riverificare prima di firmare.
> Il dimensionamento è dedotto leggendo i file in `docker/production/` di
> `matches_api` e `matches_dashboard`. Dove ho speculato, è marcato **[stima]**.
>
> **Decisione presa:** i backup vanno su **Object Storage OVH** (S3 compatibile),
> non su un MinIO self-hosted. Vedi §5 per il perché e per la configurazione.

---

## 1. Cosa deve girare sul VPS

Non esiste (più) un `compose.production.yml` nel repo — nonostante quanto scritto in `CLAUDE.md`. La composizione qui
sotto è ricostruita dai Dockerfile,
`supervisord.conf`, `php-fpm.conf`, `php.ini`, `entrypoint.sh` e
`routes/console.php`.

| Container           | Immagine                                                                                | Processi                                                             | Note                                                                                                                                                              |
|---------------------|-----------------------------------------------------------------------------------------|----------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `matches-api`       | build da `docker/production/Dockerfile` (`php:8.5-fpm-bookworm`)                        | nginx, php-fpm, **Horizon**, **Reverb**, **scheduler** (supervisord) | Espone `:80` (API + `/horizon` + `/log-viewer`) e `:8080` (websocket)                                                                                             |
| `matches-dashboard` | build da `../matches_dashboard/docker/production/Dockerfile` (`nginx:1.27-alpine-slim`) | nginx                                                                | SPA statica pre-generata (`nuxt generate`, `ssr:false`), ~15 MB, `:8080` non privilegiata                                                                         |
| `postgres`          | `postgres:17-alpine` **[stima]**                                                        | postgres                                                             | Il client nell'immagine API è `postgresql-client-18`, quindi il server può essere ≤18                                                                             |
| `redis`             | `redis:7-alpine` **[stima]**                                                            | redis-server                                                         | cache + sessioni + code (Horizon)                                                                                                                                 |
| reverse proxy       | Caddy o nginx + Let's Encrypt **[stima]**                                               | 1 processo                                                           | Termina il TLS: entrambe le immagini app sono esplicitamente progettate per stare *dietro* un proxy (nessun `server_name`, nessun certificato dentro le immagini) |

**Nessun container di storage.** L'Object Storage è un servizio OVH esterno: il VPS ci parla via HTTPS con le
credenziali S3. Un container in meno da aggiornare, monitorare e riavviare, e ~300 MB di RAM che restano all'app.

Il routing per hostname è a carico del proxy:

```
api.<domain>       -> matches-api:80
reverb.<domain>    -> matches-api:8080
dashboard.<domain> -> matches-dashboard:8080
```

---

## 2. Footprint RAM — calcolato dai file di config

### 2.1 Container `matches-api`

I limiti sono espliciti nei file, quindi il *worst case* non è speculazione:

| Processo                    | Fonte                                                                                | Limite dichiarato | Worst case         | RSS realistico **[stima]**                                                                 |
|-----------------------------|--------------------------------------------------------------------------------------|-------------------|--------------------|--------------------------------------------------------------------------------------------|
| php-fpm pool                | `php-fpm.conf:pm.max_children = 8` × `php.ini:memory_limit=256M`                     | 8 × 256 MB        | **2048 MB**        | 8 × 70–90 MB = **560–720 MB** sotto carico; 2 worker idle (`pm.start_servers=2`) = ~160 MB |
| OPcache + JIT               | `php.ini` `memory_consumption=192` + `jit_buffer_size=64M` + `interned_strings=16`   | —                 | **~272 MB** shared | ~272 MB (memoria condivisa, contata **una volta**, non per worker)                         |
| Horizon                     | `config/horizon.php` prod: `maxProcesses=10`, `memory=128`, master `memory_limit=64` | 64 + 10 × 128 MB  | **1344 MB**        | `minProcesses=1` → **~120 MB** idle; sotto burst 3–5 worker × 55–70 MB = **~300 MB**       |
| Reverb                      | processo singolo, nessun limite impostato                                            | illimitato        | —                  | **50–90 MB** base, + ~15–25 KB per connessione websocket                                   |
| Scheduler (`schedule:work`) | `supervisord.conf`                                                                   | —                 | —                  | **~30 MB**, con fork temporanei (`backup:run` → `pg_dump` + zip + upload S3)               |
| nginx                       | `worker_processes auto`                                                              | —                 | —                  | **10–25 MB**                                                                               |
| supervisord                 | —                                                                                    | —                 | —                  | ~15 MB                                                                                     |

**Totale container API [stima]:**

- idle: `160 + 272 + 120 + 70 + 30 + 20 + 15` ≈ **~690 MB**
- carico realistico: ≈ **1.4–1.8 GB**
- worst case teorico (tutti i limiti saturi): **~3.7 GB** ← è il caso da *non* dimensionare, ma da cui proteggersi (vedi
  §6)

Il commento in `php-fpm.conf` lo dice già: `pm.max_children` è la prima manopola da abbassare su un VPS piccolo, perché
un OOM del pool PHP uccide anche nginx, Horizon e Reverb — stanno tutti nello stesso container.

### 2.2 Gli altri container **[stima]**

| Container                   | RSS idle | RSS carico | Note                                                                                                        |
|-----------------------------|----------|------------|-------------------------------------------------------------------------------------------------------------|
| postgres:17-alpine          | ~120 MB  | 250–400 MB | `shared_buffers` default 128 MB; DB piccolo (vedi §4)                                                       |
| redis:7-alpine              | ~15 MB   | 80–150 MB  | dataset minuscolo; **imposta `maxmemory` + `allkeys-lru`**, altrimenti Redis è l'unico processo senza tetto |
| matches-dashboard           | ~8 MB    | ~15 MB     | solo file statici + `gzip_static` (nessuna compressione a runtime)                                          |
| reverse proxy               | ~25 MB   | ~50 MB     | Caddy                                                                                                       |
| dockerd + containerd        | ~120 MB  | ~150 MB    |                                                                                                             |
| OS (Debian/Ubuntu minimale) | ~200 MB  | ~250 MB    |                                                                                                             |

### 2.3 Totale

| Scenario                                                       | RAM             |
|----------------------------------------------------------------|-----------------|
| Idle (nessun torneo in corso)                                  | **~1.1 GB**     |
| Carico normale (un torneo live, websocket aperti, qualche PDF) | **~2.0–2.5 GB** |
| Picco (burst di code + generazione PDF + backup notturno)      | **~2.9–3.5 GB** |

---

## 3. Footprint CPU **[stima]**

Concorrenza massima progettata: 8 worker php-fpm + 10 worker Horizon + Reverb + scheduler = **20 processi PHP
potenzialmente runnable**. In pratica il carico è a raffiche e legato agli eventi:

- **Baseline**: <5% di 2 vCore. L'app è un gestionale per tornei, non un sito ad alto traffico.
- **Generazione PDF** (`spatie/laravel-pdf` + DOMPDF, driver dichiarato nel Dockerfile): CPU-bound, sincrono nella
  request. La scheda `tournament-detailed` per un torneo grande è il singolo picco più caro.
- **Matchmaking** (`MatchmakingService`, transazione DB): breve ma bloccante.
- **Backup notturno** 01:30 (`routes/console.php`): `pg_dump` + zip, un core saturo per la durata del dump. Con
  destinazione S3 si aggiunge l'upload, che è I/O e non CPU.
- **Build immagini**: `pnpm generate` della dashboard richiede `NODE_OPTIONS=--max-old-space-size=4096`. **Non buildare
  sul VPS di produzione** — 4 GB di heap Node più il resto dello stack fa fuori qualunque taglia sensata. Buildare in CI
  e fare `docker pull`.

**Conclusione: 2 vCore bastano al traffico atteso ma non lasciano margine durante il backup o un PDF pesante. 4 vCore è
la scelta sensata.**

---

## 4. Footprint disco **[stima]**

### Immagini

| Immagine                                                                            | Dimensione                                                                       |
|-------------------------------------------------------------------------------------|----------------------------------------------------------------------------------|
| `matches-api` (php:8.5-fpm-bookworm + estensioni + vendor + `postgresql-client-18`) | **~900 MB – 1.2 GB**                                                             |
| `matches-dashboard`                                                                 | ~15 MB (dichiarato nel suo README)                                               |
| postgres:17-alpine                                                                  | ~250 MB                                                                          |
| redis:7-alpine                                                                      | ~40 MB                                                                           |
| Caddy                                                                               | ~50 MB                                                                           |
| **Totale immagini**                                                                 | **~1.3–1.6 GB** (×2 se tieni la versione precedente per il rollback → **~3 GB**) |

### Dati sul VPS

| Volume                                                       | Dimensione                                                                                                                |
|--------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|
| PGDATA                                                       | **<500 MB** per anni. Il dominio è piccolo: tornei, atleti, iscrizioni, match record. Nessuna tabella ad alta cardinalità |
| Redis (AOF/RDB)                                              | <100 MB                                                                                                                   |
| `storage/logs` (`LOG_CHANNEL=daily`, letto da `/log-viewer`) | **~50–500 MB/anno** — nessuna rotazione oltre i giorni di Laravel: mettici un tetto                                       |
| `storage/app/backup-temp`                                    | dump temporaneo, transitorio: liberato dopo l'upload su S3                                                                |
| Media (foto atleti, cover tornei)                            | **0 sul VPS** — vanno su Object Storage, vedi §5.3                                                                        |

**Totale disco realistico: 5–8 GB.** Anche il taglio più piccolo (40 GB) è sovrabbondante — a patto di non buildare sul
VPS.

---

## 5. Backup su Object Storage OVH

### 5.1 Perché non MinIO

MinIO self-hosted sullo stesso VPS del database **non è un backup**: se perdi il VPS (disco, compromissione,
cancellazione account) perdi il database *e* i suoi backup nello stesso istante. Aggiungeva ~300 MB di RAM baseline, un
container da mantenere e zero protezione dal caso che conta.

L'Object Storage OVH risolve entrambe le cose: la copia è **fuori dal VPS** e non c'è nulla da gestire.
`spatie/laravel-backup` scrive già su un disco `s3`
(`config/filesystems.php:50`) — non serve toccare una riga di codice applicativo, solo variabili d'ambiente.

### 5.2 Configurazione

Il disco è già definito. `config/backup.php:168` legge `BACKUP_DISK` con default
`s3`, quindi basta popolare le `AWS_*` che `config/filesystems.php` si aspetta:

```dotenv
BACKUP_DISK=s3

AWS_ACCESS_KEY_ID=<S3 access key del user OVH>
AWS_SECRET_ACCESS_KEY=<S3 secret key>
AWS_DEFAULT_REGION=sbg              # o gra / de / waw — deve combaciare con l'endpoint
AWS_ENDPOINT=https://s3.sbg.io.cloud.ovh.net
AWS_BUCKET=matches-backups
AWS_USE_PATH_STYLE_ENDPOINT=false   # OVH supporta il virtual-hosted style; metti true se il bucket-in-host fallisce
```

Tre note operative:

- **`routes/console.php` non pianifica nulla se il bucket non è configurato**
  (`filled(config("filesystems.disks.{$backupDisk}.bucket"))`). Se dopo il deploy
  `backup:run` non gira, la variabile mancante è `AWS_BUCKET` — non è un errore silenzioso di Laravel, è una guardia
  voluta.
- **Le credenziali S3 su OVH sono legate a un utente Public Cloud**, non all'account: creane uno dedicato con il solo
  accesso al bucket dei backup.
- **`backup:monitor` gira alle 02:00** e notifica se l'ultimo backup è troppo vecchio o troppo piccolo: è il tuo canary
  sull'upload. Va configurato un canale di notifica reale (mail è già cablata), altrimenti il monitor parla nel vuoto.

### 5.3 Quanto spazio serve — e quanto costa

Dalla configurazione reale (`config/backup.php`):

- `source.files.include => []` — **nessun file nel backup**, solo il database.
- `keep_all_backups_for_days => 5`, e **tutti** gli altri livelli (`daily`/`weekly`/`monthly`/`yearly`) a **0**.
- Un backup al giorno (`backup:run` 01:30), pulizia alle 01:00, monitor alle 02:00.

Retention effettiva: **~5–6 archivi**, ciascuno uno zip di un dump Postgres.

| Dump SQL non compresso **[stima]** | Zip (~10:1) | 6 copie | Costo/mese              |
|------------------------------------|-------------|---------|-------------------------|
| 20 MB (realistico oggi)            | ~2 MB       | ~12 MB  | **arrotondato a 1 GiB** |
| 200 MB (anni di dati)              | ~20 MB      | ~120 MB | **arrotondato a 1 GiB** |
| 1 GB (pessimistico)                | ~100 MB     | ~600 MB | **arrotondato a 1 GiB** |

Object Storage Standard: **~$0,0081/GiB/mese** (≈ **€0,0075**), fatturato con granularità 1 GiB, **nessun costo di
egress e nessun costo per chiamata API**
(OVH ha eliminato le egress fee da dicembre 2025). La granularità di 1 GiB fa da minimo di fatturazione: in tutti gli
scenari plausibili si paga **1 GiB → ~€0,01 al mese**, ossia **~€0,10/anno**.

In pratica: **i backup sono gratis.** Il costo di questa scelta è zero e il guadagno è che la copia esiste anche quando
il VPS non esiste più.

Se vuoi una retention più lunga (es. `keep_monthly_backups_for_months => 12`), il conto resta nell'ordine di **1–3 GiB →
sotto €0,30/anno**. Alzarla è la cosa più economica che puoi fare a questo stack.

### 5.4 I media: stessa decisione, bucket diverso

`spatie/laravel-medialibrary` scrive sul disco letto da
`config/media-library.php:36` → `MEDIA_DISK`, con fallback a `FILESYSTEM_DISK` e poi a `public` (**disco locale**). Se
non lo imposti, le foto atleti e le cover torneo finiscono su `storage/app/public` del VPS — e **non sono nel backup**,
perché `source.files.include` è vuoto.

Ora che l'Object Storage c'è, la scelta coerente è usarlo anche per i media, in un **bucket separato** da quello dei
backup (permessi e lifecycle diversi):

```dotenv
FILESYSTEM_DISK=s3
MEDIA_DISK=s3
```

Se il disco `s3` è condiviso tra media e backup, i backup finiranno nello stesso bucket dei media: se vuoi separarli
davvero, aggiungi un secondo disco (`s3_backups`) in `config/filesystems.php` e punta `BACKUP_DISK` a quello.

Volume media **[stima]**: una foto atleta a ~200–500 KB, `upload_max_filesize` è 100 MB (`php-fpm.conf` / `nginx.conf`)
ma le foto reali sono piccole. Anche con 5.000 atleti si sta **sotto 2–3 GiB → ~€0,25/anno**. Anche qui, irrilevante.

Alternativa se vuoi tenere i media sul VPS: `FILESYSTEM_DISK=public` + volume persistente, **e allora aggiungi
`storage_path('app/public')` a
`config/backup.php` → `source.files.include`**, altrimenti sono fuori dal backup.

---

## 6. Taglia consigliata e costi

Prezzi OVHcloud "VPS 2027", **IVA esclusa**, **impegno 12 mesi** (rilevati 2026-08-25):

| Modello   | vCore | RAM      | NVMe      | Banda      | €/mese   | €/anno    |
|-----------|-------|----------|-----------|------------|----------|-----------|
| VPS-1     | 2     | 4 GB     | 40 GB     | 500 Mbps   | 3,81     | 45,72     |
| **VPS-2** | **4** | **8 GB** | **75 GB** | **1 Gbps** | **7,21** | **86,52** |
| VPS-3     | 6     | 12 GB    | 100 GB    | 2 Gbps     | 10,40    | 124,80    |
| VPS-4     | 8     | 24 GB    | 200 GB    | 3 Gbps     | 19,96    | 239,52    |

Traffico illimitato e backup giornaliero (retention 24h) inclusi in tutti i tagli.

### Verdetto

**VPS-2 (4 vCore / 8 GB / 75 GB) — €7,21/mese ex IVA.**

Perché non VPS-1 (4 GB): il picco stimato è **2.9–3.5 GB** e i limiti *dichiarati*
nei file di config sommano a 3.7 GB solo per il container API. Su 4 GB ci sta, ma senza margine: il primo OOM uccide
nginx + Horizon + Reverb insieme, perché condividono il container. VPS-1 diventa una scelta ragionevole **solo**
abbassando i limiti (vedi sotto) — e va bene per staging.

Perché non VPS-3+: 12 GB e 6 vCore non hanno nulla da fare per un gestionale tornei. Si sale solo se arrivano più tornei
live in parallelo con molti websocket aperti.

### Costo totale annuo

| Voce                                                                                | €/mese ex IVA | €/anno ex IVA       |
|-------------------------------------------------------------------------------------|---------------|---------------------|
| VPS-2 (impegno 12 mesi)                                                             | 7,21          | 86,52               |
| Backup automatico Premium OVH, retention 7 giorni — *opzionale, copre la VM intera* | 1,10          | 13,20               |
| Snapshot pre-deploy — *opzionale*                                                   | 0,30          | 3,60                |
| Object Storage — backup DB (1 GiB)                                                  | ~0,01         | ~0,10               |
| Object Storage — media (2–3 GiB) **[stima]**                                        | ~0,02         | ~0,25               |
| TLS (Let's Encrypt via Caddy)                                                       | 0,00          | 0,00                |
| Dominio `.it` **[stima, non OVH-specifico]**                                        | —             | 10–15               |
| **Totale**                                                                          | **~8,6**      | **~114–119 ex IVA** |

Con **IVA italiana al 22%**: **~€10,5/mese**, **~€139–145/anno**.

Lo storage è **rumore di fondo (<€0,50/anno)**: tutto il costo è il VPS. Non ottimizzare la retention dei backup per
risparmiare — non c'è nulla da risparmiare.

Registry immagini: se usi GitHub/Codeberg Container Registry sei a €0. Il
`Makefile` (`make release V=...`) suggerisce già un flusso tag-based da CI. Nota: OVH regala **€200 di credito** ai
nuovi account Public Cloud — a questi volumi copre lo storage per decenni.

### Se vuoi stare su VPS-1 (4 GB, €45,72/anno)

Tre modifiche, tutte in file già esistenti:

1. `docker/production/php-fpm.conf`: `pm.max_children = 8` → **4** (worst case pool: 2048 → 1024 MB).
2. `config/horizon.php`, blocco `production`: `maxProcesses = 10` → **4** (worst case Horizon: 1344 → 576 MB).
3. `redis`: `--maxmemory 256mb --maxmemory-policy allkeys-lru`.

Con queste, il picco scende a **~2.0 GB** e 4 GB diventano confortevoli. Il costo è una coda di richieste più lunga
sotto burst — accettabile per il traffico atteso.

---

## 7. Cosa questo documento *non* copre

- **Nessun `compose.production.yml` nel repo.** Va scritto (o il deploy va documentato) prima di considerare questa
  stima azionabile.
- **Nessuna misura reale.** Ogni **[stima]** di RAM/CPU/disco va confermata con `docker stats` e `pg_database_size()`
  dopo il primo torneo in produzione. La stima serve a scegliere la taglia, non a sostituire il monitoring.
- **Il restore non è testato.** Un backup su Object Storage vale quanto l'ultimo `backup:restore` che hai provato. Va
  fatto almeno una volta, verso un DB di staging.
- **Alta disponibilità: zero.** Un VPS, un Postgres, un Redis. Il deploy ha downtime (`entrypoint.sh` fa
  `artisan migrate --force` al boot). Per un gestionale tornei è probabilmente accettabile; va detto, non scoperto
  durante l'evento.

## Fonti

- [OVHcloud VPS — pagina prezzi IT](https://www.ovhcloud.com/it/vps/)
- [OVHcloud VPS — pagina prezzi EN](https://www.ovhcloud.com/en-ie/vps/)
- [OVHcloud Object Storage S3 — nessuna egress fee](https://www.ovhcloud.com/it/public-cloud/object-storage/)
- [OVHcloud Object Storage — FAQ S3 (endpoint, region)](https://docs.ovhcloud.com/en/guides/storage-and-backup/object-storage/s3-faq)
- [Standard Object Storage OVHcloud — stima prezzo/GB](https://pcr.cloud-mercato.com/providers/ovh/object-storage/ovh-obs-std/pricing)
- [OVHcloud — evoluzione prezzi 2026](https://blog.ovhcloud.com/en/posts/pricing-evolution-of-public-cloud-bare-metal-and-vps-at-ovhcloud/)
