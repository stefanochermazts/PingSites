@use('App\Support\DisplayDate')
@extends('status.layout', ['title' => $monitor['name'].' — '.$title])

@section('body')
    <header class="status-mast">
        <div class="status-mast__inner">
            <div>
                <a href="{{ route('status.show', $status_page['slug']) }}" class="status-action status-action--back status-mast__back">&larr; Torna alla status page</a>
                <div class="status-mast__identity">
                    <h1 class="status-mast__title">{{ $monitor['name'] }}</h1>
                    @include('status.partials.status-badge', [
                        'status' => $monitor['status'],
                        'label' => $monitor['status_label'],
                    ])
                </div>
                @if(!empty($monitor['url']))
                    <p>
                        <a
                            href="{{ $monitor['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="status-service__url"
                        >
                            {{ $monitor['url'] }}
                            <span class="sr-only"> (si apre in una nuova scheda)</span>
                        </a>
                    </p>
                @endif
            </div>
            <div class="status-mast__readout">
                @if($monitor['last_checked_at'])
                    <p class="status-stamp">
                        Ultimo controllo {{ DisplayDate::format($monitor['last_checked_at'], 'd/m/Y H:i') }}
                        · {{ DisplayDate::parse($monitor['last_checked_at'])?->diffForHumans() }}
                    </p>
                @endif
            </div>
        </div>
    </header>

    <main id="contenuto" class="status-deck">
        <section class="status-panel status-instruments" aria-label="Indicatori del servizio">
            <div class="status-instrument">
                <p class="status-instrument__label">Disponibilità</p>
                <p @class([
                    'status-instrument__value',
                    'status-uptime--ok' => ($stats['uptime_percent'] ?? 0) >= 99,
                    'status-uptime--warn' => ($stats['uptime_percent'] ?? 0) >= 95 && ($stats['uptime_percent'] ?? 0) < 99,
                    'status-uptime--down' => ($stats['uptime_percent'] ?? 0) < 95,
                    'status-muted' => $stats['uptime_percent'] === null,
                ])>
                    @if($stats['uptime_percent'] !== null)
                        {{ number_format($stats['uptime_percent'], 1, ',', '.') }}%
                    @else
                        —
                    @endif
                </p>
                <p class="status-instrument__hint">Ultime {{ $stats['sample_size'] }} esecuzioni</p>
            </div>
            <div class="status-instrument">
                <p class="status-instrument__label">Tempo medio risposta</p>
                <p class="status-instrument__value">
                    @if($stats['avg_response_time_ms'])
                        {{ number_format($stats['avg_response_time_ms'], 0, ',', '.') }} ms
                    @else
                        —
                    @endif
                </p>
                <p class="status-instrument__hint">Solo check riusciti</p>
            </div>
            <div class="status-instrument">
                <p class="status-instrument__label">Ultima risposta</p>
                <p class="status-instrument__value">
                    @if($monitor['last_response_time_ms'])
                        {{ number_format($monitor['last_response_time_ms'], 0, ',', '.') }} ms
                    @else
                        —
                    @endif
                </p>
            </div>
        </section>

        @if(count($checks) > 0)
            @php
                $successfulChecks = collect($checks)->where('success', true)->count();
                $failedChecks = count($checks) - $successfulChecks;
                $chartLabel = 'Grafico dei tempi di risposta delle ultime '.count($checks).' esecuzioni.';
                $timelineLabel = 'Timeline disponibilità: '.count($checks).' controlli, '.$successfulChecks.' riusciti, '.$failedChecks.' non disponibili.';
            @endphp
            <section class="status-panel" aria-labelledby="tempi-heading">
                <div class="status-panel__body">
                    <h2 id="tempi-heading" class="status-panel__title">Tempi di risposta</h2>
                    <p class="status-caption">Ultime {{ count($checks) }} esecuzioni, dalla più vecchia alla più recente.</p>
                    <div class="status-chart">
                        <canvas
                            id="response-time-chart"
                            role="img"
                            aria-label="{{ $chartLabel }}"
                        ></canvas>
                    </div>
                    <p class="status-instrument__label status-timeline-heading">Timeline disponibilità</p>
                    <div
                        class="status-timeline"
                        role="img"
                        aria-label="{{ $timelineLabel }}"
                    >
                        @foreach(array_reverse($checks) as $check)
                            <div
                                @class([
                                    'status-timeline__seg',
                                    'status-timeline__seg--ok' => $check['success'],
                                    'status-timeline__seg--down' => ! $check['success'],
                                ])
                                title="{{ DisplayDate::format($check['checked_at'], 'd/m/Y H:i') }} — {{ $check['status_label'] }}"
                            ></div>
                        @endforeach
                    </div>
                    <div class="status-timeline__axis">
                        <span>Più vecchio</span>
                        <span>Ora</span>
                    </div>
                </div>
            </section>

            <section class="status-panel" aria-labelledby="esecuzioni-heading">
                <div class="status-panel__toolbar">
                    <h2 id="esecuzioni-heading" class="status-panel__title">Ultime esecuzioni</h2>
                </div>
                <div class="status-table-wrap">
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>Data e ora</th>
                                <th>Esito</th>
                                <th>Tempo risposta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($checks as $check)
                                <tr>
                                    <td class="is-num">
                                        {{ DisplayDate::format($check['checked_at'], 'd/m/Y H:i:s') }}
                                    </td>
                                    <td>
                                        @include('status.partials.status-badge', [
                                            'status' => $check['success'] ? 'operational' : 'down',
                                            'label' => $check['status_label'],
                                        ])
                                    </td>
                                    <td class="is-num">
                                        @if($check['response_time_ms'])
                                            {{ number_format($check['response_time_ms'], 0, ',', '.') }} ms
                                        @else
                                            <span class="is-empty">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="status-panel">
                <div class="status-panel__body status-panel__empty">
                    Nessun controllo registrato per questo servizio.
                </div>
            </section>
        @endif
    </main>
@endsection

@if(count($checks) > 0)
    @push('head')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/js/status-chart.js'])
        @endif
    @endpush
    @push('scripts')
        <script type="application/json" id="status-chart-data">@json($chart)</script>
    @endpush
@endif
