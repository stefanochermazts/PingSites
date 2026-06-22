<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $monitor['name'] }} — {{ $title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-900">
<div class="min-h-screen">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-5xl px-4 py-8">
            <a href="{{ route('status.show', $status_page['slug']) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">&larr; Torna alla status page</a>
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <h1 class="text-3xl font-bold">{{ $monitor['name'] }}</h1>
                @include('status.partials.status-badge', [
                    'status' => $monitor['status'],
                    'label' => $monitor['status_label'],
                ])
            </div>
            @if($monitor['last_checked_at'])
                <p class="mt-2 text-sm text-slate-500">
                    Ultimo controllo: {{ \Illuminate\Support\Carbon::parse($monitor['last_checked_at'])->format('d/m/Y H:i') }}
                    ({{ \Illuminate\Support\Carbon::parse($monitor['last_checked_at'])->diffForHumans() }})
                </p>
            @endif
        </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-8 px-4 py-8">
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Disponibilità</p>
                <p class="mt-1 text-2xl font-bold @class([
                    'text-emerald-600' => ($stats['uptime_percent'] ?? 0) >= 99,
                    'text-amber-600' => ($stats['uptime_percent'] ?? 0) >= 95 && ($stats['uptime_percent'] ?? 0) < 99,
                    'text-red-600' => ($stats['uptime_percent'] ?? 0) < 95,
                    'text-slate-400' => $stats['uptime_percent'] === null,
                ])">
                    @if($stats['uptime_percent'] !== null)
                        {{ number_format($stats['uptime_percent'], 1, ',', '.') }}%
                    @else
                        —
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-400">Ultime {{ $stats['sample_size'] }} esecuzioni</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Tempo medio risposta</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">
                    @if($stats['avg_response_time_ms'])
                        {{ number_format($stats['avg_response_time_ms'], 0, ',', '.') }} ms
                    @else
                        —
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-400">Solo check riusciti</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Ultima risposta</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">
                    @if($monitor['last_response_time_ms'])
                        {{ number_format($monitor['last_response_time_ms'], 0, ',', '.') }} ms
                    @else
                        —
                    @endif
                </p>
            </div>
        </section>

        @if(count($checks) > 0)
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-2 text-xl font-semibold">Tempi di risposta</h2>
                <p class="mb-6 text-sm text-slate-500">Ultime {{ count($checks) }} esecuzioni, dalla più vecchia alla più recente.</p>
                <div class="mb-6 h-64">
                    <canvas id="response-time-chart"></canvas>
                </div>
                <div>
                    <p class="mb-2 text-sm font-medium text-slate-600">Timeline disponibilità</p>
                    <div class="flex h-8 overflow-hidden rounded-lg border border-slate-200">
                        @foreach(array_reverse($checks) as $check)
                            <div
                                class="flex-1 @if($check['success']) bg-emerald-500 @else bg-red-500 @endif"
                                title="{{ \Illuminate\Support\Carbon::parse($check['checked_at'])->format('d/m/Y H:i') }} — {{ $check['status_label'] }}"
                            ></div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-slate-400">
                        <span>Più vecchio</span>
                        <span>Ora</span>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xl font-semibold">Ultime esecuzioni</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-500">
                                <th class="pb-3 pr-4 font-medium">Data e ora</th>
                                <th class="pb-3 pr-4 font-medium">Esito</th>
                                <th class="pb-3 font-medium">Tempo risposta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($checks as $check)
                                <tr>
                                    <td class="py-3 pr-4 text-slate-700">
                                        {{ \Illuminate\Support\Carbon::parse($check['checked_at'])->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        @include('status.partials.status-badge', [
                                            'status' => $check['success'] ? 'operational' : 'down',
                                            'label' => $check['status_label'],
                                        ])
                                    </td>
                                    <td class="py-3 text-slate-700">
                                        @if($check['response_time_ms'])
                                            {{ number_format($check['response_time_ms'], 0, ',', '.') }} ms
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-6 text-slate-500 shadow-sm">
                Nessun controllo registrato per questo servizio.
            </section>
        @endif
    </main>
</div>

@if(count($checks) > 0)
<script>
    const chartLabels = @json($chart['labels']);
    const chartResponseTimes = @json($chart['response_times']);
    const chartSuccess = @json($chart['success']);

    const pointColors = chartSuccess.map(success => success ? 'rgb(16, 185, 129)' : 'rgb(239, 68, 68)');

    new Chart(document.getElementById('response-time-chart'), {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Tempo risposta (ms)',
                data: chartResponseTimes,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                pointBackgroundColor: pointColors,
                pointBorderColor: pointColors,
                pointRadius: 4,
                tension: 0.25,
                fill: true,
                spanGaps: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const ms = context.parsed.y;
                            if (ms === null) {
                                return 'Non disponibile';
                            }
                            return ms + ' ms';
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'ms' },
                },
            },
        },
    });
</script>
@endif
</body>
</html>
