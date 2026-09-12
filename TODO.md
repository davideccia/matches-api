# TODO — privacy / GDPR (backend)

Estratto da `matches-dashboard/TODO.md` il 2026-09-12, durante la revisione
GDPR di `public/privacy.txt` (frontend) contro il comportamento reale del
codice. Le voci qui sotto sono cambiamenti di codice che riguardano
esclusivamente questo repository (Laravel API) — le voci di solo frontend, le
decisioni del Titolare e le informativa restano in `matches-dashboard/TODO.md`.

---

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

---

## Nota

Questa lista nasce da una verifica tecnica codice-vs-documento condotta sul
repository frontend (`matches-dashboard`), non da una validazione legale. Le
scelte di base giuridica e i termini di conservazione sono decisioni del
Titolare, non tecniche.
