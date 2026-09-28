# Web Push — Backend (Laravel API, repo separato)

Pacchetto: `laravel-notification-channels/webpush` (wrapper su Minishlink/WebPush), integrato col sistema `Notification` esistente.

## Setup

```bash
composer require laravel-notification-channels/webpush
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider"
php artisan migrate
php artisan webpush:vapid
```

- La migration crea `push_subscriptions` (polimorfica, collegata a `User`).
- `webpush:vapid` genera `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` in `.env`. La public key va condivisa col frontend (serve a `pushManager.subscribe()`).
- `User` implementa il trait `HasPushSubscriptions`.

## Endpoint da esporre

- `POST /push-subscriptions` — salva `endpoint`, `keys.p256dh`, `keys.auth` → `$user->updatePushSubscription(...)`
- `DELETE /push-subscriptions` — rimuove la subscription del browser corrente → `$user->deletePushSubscription($endpoint)`
- `DELETE /tournaments/{id}/subscription` — se il modello è "segui questo torneo": rimuove solo l'interesse per quel torneo (tabella pivot `tournament_user_subscriptions`), non tocca la subscription push del browser

## Notification class

```php
class TournamentConcludedNotification extends Notification implements ShouldQueue
{
    public function via($notifiable) { return [WebPushChannel::class]; }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Torneo concluso')
            ->body(...)
            ->icon(url('/api/public/settings/logo')) // stesso endpoint pubblico usato dal logo dinamico frontend
            ->action('Apri', 'open');
    }
}
```

Va agganciata (via) al `ShouldQueue` — serve un queue worker attivo, non `sync`.

## Notifica finale su stato "concluso", poi stop

Nessuna gestione speciale di "stop": basta che la notifica sia legata alla **transizione** di stato, non a un job ricorrente.

```php
if ($tournament->status === TournamentStatus::CONCLUDED) {
    $tournament->subscribedUsers()->each(
        fn ($user) => $user->notify(new TournamentConcludedNotification($tournament))
    );
}
```

Se nessun altro punto del codice invia notifiche per un torneo `concluded`, non arriva più nulla — non serve un flag esplicito.

**Rischio da coprire**: doppio invio se l'evento/job viene rieseguito (retry di coda, webhook duplicato). Due opzioni:

- guard sulla transizione: `if ($tournament->wasChanged('status') && ...)`
- campo `concluded_notification_sent_at`, controllato prima dell'invio

## Subscription invalide

Il pacchetto gestisce l'errore `410 Gone` (endpoint revocato dal browser) e può rimuovere la subscription automaticamente — verificare la configurazione, altrimenti gestirlo nel job di invio.

## Modello dati consigliato (caso "segui torneo")

- `push_subscriptions` — una per browser/dispositivo dell'utente (dal pacchetto)
- `tournament_user_subscriptions` (pivot) — interesse utente↔torneo, usata per filtrare `subscribedUsers()` invece di notificare tutti gli utenti con push attiva
