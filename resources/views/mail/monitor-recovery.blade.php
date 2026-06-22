<x-mail::message>
# Sito tornato online

Il monitor **{{ $monitor->name }}** è di nuovo disponibile.

**URL:** {{ $monitor->url }}

**Ripristino:** {{ $incident->closed_at?->format('d/m/Y H:i:s') }}

**Durata incidente:** {{ $incident->duration_seconds ? gmdate('H:i:s', $incident->duration_seconds) : 'N/D' }}

**Causa iniziale:** {{ $incident->initial_cause }}

**Ultimo tempo di risposta:** {{ $monitor->last_response_time_ms ? $monitor->last_response_time_ms.' ms' : 'N/D' }}

<x-mail::button :url="$adminUrl">
Dettagli incidente
</x-mail::button>

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
