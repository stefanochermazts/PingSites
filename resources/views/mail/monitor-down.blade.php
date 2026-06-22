<x-mail::message>
# Sito non disponibile

Il monitor **{{ $monitor->name }}** non risponde correttamente.

**URL:** {{ $monitor->url }}

**Data/ora:** {{ $incident->opened_at->format('d/m/Y H:i:s') }}

**Errore:** {{ $incident->initial_cause }}

**Codice HTTP:** {{ $monitor->last_http_code ?? 'N/D' }}

**Check falliti consecutivi:** {{ $incident->failed_checks_count }}

<x-mail::button :url="$adminUrl">
Apri incidente
</x-mail::button>

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
