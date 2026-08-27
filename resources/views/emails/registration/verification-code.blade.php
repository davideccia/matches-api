<x-mail::message>
# Codice di verifica

Hai ricevuto questa email perché è stata avviata un'iscrizione a un torneo con questo indirizzo.
Inserisci il codice qui sotto per confermarla.

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

Il codice scade tra {{ $ttlMinutes }} minuti e può essere usato una sola volta.

Se non hai richiesto tu l'iscrizione, ignora questa email: senza il codice non verrà completata.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
