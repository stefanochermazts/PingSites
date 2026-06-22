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
        <div class="mx-auto max-w-4xl px-4 py-8">
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

    <main class="mx-auto max-w-4xl space-y-8 px-4 py-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xl font-semibold">Servizi</h2>
            <ul class="divide-y divide-slate-100">
                @forelse($monitors as $monitor)
                    <li class="flex items-center justify-between py-3">
                        <span class="font-medium">{{ $monitor['name'] }}</span>
                        <span @class([
                            'rounded-full px-3 py-1 text-sm font-medium',
                            'bg-emerald-100 text-emerald-700' => $monitor['status'] === 'operational',
                            'bg-amber-100 text-amber-700' => $monitor['status'] === 'maintenance',
                            'bg-red-100 text-red-700' => $monitor['status'] === 'down',
                            'bg-slate-100 text-slate-600' => $monitor['status'] === 'unknown',
                        ])>{{ $monitor['status_label'] }}</span>
                    </li>
                @empty
                    <li class="py-3 text-slate-500">Nessun servizio pubblicato.</li>
                @endforelse
            </ul>
        </section>

        @if($open_incidents->isNotEmpty())
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

        @if($maintenances->isNotEmpty())
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="mb-4 text-xl font-semibold text-amber-900">Manutenzioni</h2>
                <ul class="space-y-4">
                    @foreach($maintenances as $maintenance)
                        <li>
                            <p class="font-medium text-amber-900">{{ $maintenance['title'] }}</p>
                            <p class="text-amber-800">{{ $maintenance['message'] }}</p>
                            <p class="text-sm text-amber-700">
                                {{ $maintenance['starts_at']->format('d/m/Y H:i') }} -
                                {{ $maintenance['ends_at']->format('d/m/Y H:i') }}
                                @if($maintenance['is_active']) (in corso) @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($recent_incidents->isNotEmpty())
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
                                {{ $incident['opened_at']->format('d/m/Y H:i') }}
                                @if($incident['closed_at'])
                                    - {{ $incident['closed_at']->format('d/m/Y H:i') }}
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
