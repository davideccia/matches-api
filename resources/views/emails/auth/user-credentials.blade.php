<x-mail::message>
# Ciao, {{ $username }}

È stato creato un account per te su {{ config('app.name') }}. Ecco la tua email di accesso:

<x-mail::panel>
**Email:** {{ $email }}
</x-mail::panel>

Per motivi di sicurezza, ti invitiamo a eseguire il flusso "Password dimenticata".

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
