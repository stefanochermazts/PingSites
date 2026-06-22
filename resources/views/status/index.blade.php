<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
<div class="min-h-screen">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-5xl px-4 py-8">
            <h1 class="text-3xl font-bold">{{ $title }}</h1>
            <p class="mt-2 text-lg @class([
                'text-emerald-600' => $overall_status === 'operational',
                'text-amber-600' => $overall_status === 'maintenance',
                'text-red-600' => $overall_status === 'degraded',
                'text-slate-500' => $overall_status === 'unavailable',
            ])">{{ $overall_status_label }}</p>
            @if($updated_at)
                <p class="mt-1 text-sm text-slate-500">Ultimo aggiornamento: {{ \Illuminate\Support\Carbon::parse($updated_at)->format('d/m/Y H:i') }}</p>
            @endif
        </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-8 px-4 py-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xl font-semibold">Servizi</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500">
                            <th class="pb-3 pr-4 font-medium">Servizio</th>
                            <th class="pb-3 pr-4 font-medium">Stato</th>
                            <th class="hidden pb-3 pr-4 font-medium sm:table-cell">Ultimo controllo</th>
                            <th class="hidden pb-3 pr-4 font-medium md:table-cell">Risposta</th>
                            <th class="hidden pb-3 pr-4 font-medium lg:table-cell">Disponibilità</th>
                            <th class="pb-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($monitors as $monitor)
                            <tr class="align-middle">
                                <td class="py-4 pr-4 font-medium">{{ $monitor['name'] }}</td>
                                <td class="py-4 pr-4">
                                    @include('status.partials.status-badge', [
                                        'status' => $monitor['status'],
                                        'label' => $monitor['status_label'],
                                    ])
                                </td>
                                <td class="hidden py-4 pr-4 text-slate-600 sm:table-cell">
                                    @if($monitor['last_checked_at'])
                                        <span title="{{ \Illuminate\Support\Carbon::parse($monitor['last_checked_at'])->format('d/m/Y H:i:s') }}">
                                            {{ \Illuminate\Support\Carbon::parse($monitor['last_checked_at'])->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden py-4 pr-4 text-slate-600 md:table-cell">
                                    @if($monitor['last_response_time_ms'])
                                        {{ number_format($monitor['last_response_time_ms'], 0, ',', '.') }} ms
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden py-4 pr-4 lg:table-cell">
                                    @if($monitor['uptime_percent'] !== null)
                                        <span @class([
                                            'font-medium',
                                            'text-emerald-600' => $monitor['uptime_percent'] >= 99,
                                            'text-amber-600' => $monitor['uptime_percent'] >= 95 && $monitor['uptime_percent'] < 99,
                                            'text-red-600' => $monitor['uptime_percent'] < 95,
                                        ])>{{ number_format($monitor['uptime_percent'], 1, ',', '.') }}%</span>
                                        <span class="text-slate-400"> / {{ $monitor['sample_size'] }} check</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-4 text-right">
                                    <a href="{{ route('status.monitor', $monitor['id']) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                        Dettaglio
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-slate-500">Nessun servizio pubblicato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-slate-400">La disponibilità è calcolata sulle ultime 30 esecuzioni per servizio.</p>
        </section>

        @if(count($open_incidents) > 0)
            <section class="rounded-xl border border-red-200 bg-red-50 p-6">
                <h2 class="mb-4 text-xl font-semibold text-red-800">Incidenti attivi</h2>
                <ul class="space-y-4">
                    @foreach($open_incidents as $incident)
                        <li>
                            <p class="font-medium text-red-900">{{ $incident['name'] }}</p>
                            <p class="text-red-700">{{ $incident['message'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(count($maintenances) > 0)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="mb-4 text-xl font-semibold text-amber-900">Manutenzioni</h2>
                <ul class="space-y-4">
                    @foreach($maintenances as $maintenance)
                        <li>
                            <p class="font-medium text-amber-900">{{ $maintenance['title'] }}</p>
                            <p class="text-amber-800">{{ $maintenance['message'] }}</p>
                            <p class="text-sm text-amber-700">
                                {{ \Illuminate\Support\Carbon::parse($maintenance['starts_at'])->format('d/m/Y H:i') }} -
                                {{ \Illuminate\Support\Carbon::parse($maintenance['ends_at'])->format('d/m/Y H:i') }}
                                @if($maintenance['is_active']) (in corso) @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(count($recent_incidents) > 0)
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xl font-semibold">Storico recente incidenti</h2>
                <ul class="divide-y divide-slate-100">
                    @foreach($recent_incidents as $incident)
                        <li class="py-3">
                            <div class="flex items-center justify-between gap-4">
                                <span class="font-medium">{{ $incident['name'] }}</span>
                                <span class="text-sm text-slate-500">{{ $incident['status'] }}</span>
                            </div>
                            <p class="text-sm text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($incident['opened_at'])->format('d/m/Y H:i') }}
                                @if($incident['closed_at'])
                                    - {{ \Illuminate\Support\Carbon::parse($incident['closed_at'])->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</div>
</body>
</html>
