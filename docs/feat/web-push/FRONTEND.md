# Web Push — Frontend (matches_dashboard)

## Cosa sono

Notifiche di sistema (Web Push), diverse dal realtime Reverb/Echo già presente:

| | Reverb/Echo (esistente) | Web Push (nuovo) |
|---|---|---|
| Richiede tab aperta | Sì | No |
| Richiede browser aperto | Sì | No (desktop Chrome/Firefox) |
| Uso | Aggiornare UI live | Avvisare anche ad app chiusa |

Complementari, non sostitutivi.

## Supporto browser

| Browser/OS | Tab chiusa | Browser chiuso | Serve PWA installata |
|---|---|---|---|
| Chrome/Firefox desktop | ✅ | ✅ | No |
| Chrome/Firefox Android | ✅ | ✅ | No |
| Safari macOS 16+ | ✅ | ✅ | No |
| Safari iOS | ✅ | ✅ | Sì (Add to Home Screen) |

Non è garanzia real-time al 100%: OS con risparmio energetico aggressivo può ritardare la consegna.

## Componenti da aggiungere

1. **Service Worker** (`public/sw.js` o via `@vite-pwa/nuxt`) — gestisce l'evento `push`, mostra la notifica di sistema. Resta attivo indipendentemente dalla tab.
2. **Composable `usePushNotifications()`**:
   - `Notification.requestPermission()`
   - `serviceWorker.register()` + `pushManager.subscribe()` (serve la **VAPID public key**, fornita dal backend)
   - invia la subscription (`endpoint`, `keys.p256dh`, `keys.auth`) via `useApi().post()`
3. **UI di iscrizione/disiscrizione**:
   - toggle globale (stop a tutte le push) → `pushManager.getSubscription().then(sub => sub.unsubscribe())` + `DELETE /push-subscriptions` lato API
   - se il modello è "segui questo torneo" (caso d'uso discusso: notifica su stato torneo concluso), la subscription push del browser resta unica; l'iscrizione/disiscrizione per singolo torneo passa da un endpoint dedicato (`DELETE /tournaments/{id}/subscription`), non tocca la subscription del browser

## Permesso

Chiesto dal browser tramite popup nativo (non personalizzabile), triggerato da `Notification.requestPermission()`. Serve un'interazione utente (click su bottone) — non chiamarlo al caricamento pagina. Tre stati da gestire in UI:

- `default` — non ancora chiesto → mostra bottone "Attiva notifiche"
- `granted` — iscritto
- `denied` — persistente, nessun retry automatico; mostrare messaggio "riabilita dalle impostazioni del browser"

## Icona e immagine della notifica

Il progetto serve già un logo custom da API (`app/composables/useAppLogo.ts`), pubblico e senza auth: `${baseUrl}/api/public/settings/logo`. Riusabile come icona della notifica — il backend include l'URL nel payload push, il Service Worker lo passa a `showNotification`:

```js
// public/sw.js
self.addEventListener('push', (event) => {
  const data = event.data.json()
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: data.icon,   // es. https://api.matches.it/api/public/settings/logo
      badge: '/icons/badge-monochrome.png', // Android-only, silhouette bianca su trasparente
      data: { url: data.url },
    })
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  event.waitUntil(clients.openWindow(event.notification.data.url))
})
```

Su iOS PWA l'icona di solito eredita quella di `manifest.json`, `icon` custom ha effetto minore.

## Note

- Il permesso può essere revocato dall'utente in qualsiasi momento dalle impostazioni del browser — gestire lo stato `Notification.permission` di conseguenza in UI.
- Panel/modal di gestione vanno wrappati in `<ClientOnly>` (coerente col resto dell'app, `ssr: false`).
