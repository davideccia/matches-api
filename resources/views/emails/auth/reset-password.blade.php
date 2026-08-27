<x-mail::message>
# Ciao, {{ $user->username }}

Hai ricevuto questa email perché è stata richiesta una reimpostazione della password per il tuo account.

<x-mail::button :url="$url">
Reimposta password
</x-mail::button>

Il link scade tra 60 minuti.

Se non hai richiesto il recupero password, ignora questa email.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
