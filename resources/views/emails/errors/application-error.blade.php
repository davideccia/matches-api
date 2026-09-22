<x-mail::message>
# Errore applicativo

**Eccezione:** {{ $exceptionClass }}

**Messaggio:** {{ $exceptionMessage }}

**File:** {{ $file }}:{{ $line }}

Consulta il log giornaliero o il log viewer per lo stack trace completo.

{{ config('app.name') }}
</x-mail::message>
