<x-mail::message>
# Ciao, {{ $username }}

È stato creato un account per te su {{ config('app.name') }}. Ecco le tue credenziali di accesso:

<x-mail::panel>
**Email:** {{ $email }}

**Password:** {{ $password }}
</x-mail::panel>

Per motivi di sicurezza, ti invitiamo a cambiare la password al primo accesso.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
