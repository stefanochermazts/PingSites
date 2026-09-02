@use('App\Support\DisplayDate')
@extends('status.layout')

@section('body')
    <header class="status-mast status-mast--signal">
        <div class="status-mast__inner">
            <h1 class="status-mast__title">{{ $title }}</h1>
            <div class="status-mast__readout">
                <p @class(['status-signal', 'status-signal--'.$overall_status])>
                    <span class="status-lamp" aria-hidden="true"></span>
                    <span>{{ $overall_status_label }}</span>
                </p>
                @if($updated_at)
                    <p class="status-stamp">Aggiornato {{ DisplayDate::format($updated_at, 'd/m/Y H:i') }}</p>
                @endif
            </div>
        </div>
        <div @class(['status-mast__rail', 'status-mast__rail--'.$overall_status]) aria-hidden="true"></div>
    </header>

    <main id="contenuto" class="status-deck">
        <section class="status-panel" aria-labelledby="servizi-heading">
            <div class="status-panel__toolbar">
                <h2 id="servizi-heading" class="status-panel__title">Servizi</h2>
                <div class="status-filters">
                    @if(!empty($status_filters))
                        <div class="status-filterbank">
                            <p class="status-filterbank__label" id="filtro-stato-label">Stato</p>
                            <nav class="status-chips" aria-labelledby="filtro-stato-label">
                                @include('status.partials.filter-chips', ['filters' => $status_filters])
                            </nav>
                        </div>
                    @endif
                    @if(!empty($publication_filters))
                        <div class="status-filterbank">
                            <p class="status-filterbank__label" id="filtro-indirizzo-label">Indirizzo</p>
                            <nav class="status-chips" aria-labelledby="filtro-indirizzo-label">
                                @include('status.partials.filter-chips', ['filters' => $publication_filters])
                            </nav>
                        </div>
                    @endif
                </div>
            </div>
            <div class="status-table-wrap">
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>Servizio</th>
                            <th>Stato</th>
                            @if(!empty($shows_infection))
                                <th>Infezione</th>
                            @endif
                            <th class="status-hide-sm">Ultimo controllo</th>
                            <th class="status-hide-md">Risposta</th>
                            <th class="status-hide-lg">Disponibilità</th>
                            <th><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($monitors as $monitor)
                            <tr>
                                <td>
                                    <div class="status-service">
                                        <div class="status-service__name">{{ $monitor['name'] }}</div>
                                        @if(!empty($monitor['url']))
                                            <a
                                                href="{{ $monitor['url'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="status-service__url"
                                                title="{{ $monitor['url'] }}"
                                            >
                                                {{ $monitor['url'] }}
                                                <span class="sr-only"> (si apre in una nuova scheda)</span>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @include('status.partials.status-badge', [
                                        'status' => $monitor['status'],
                                        'label' => $monitor['status_label'],
                                    ])
                                    @if(!empty($monitor['error_detail']))
                                        <p class="status-error">{{ $monitor['error_detail'] }}</p>
                                    @endif
                                </td>
                                @if(!empty($shows_infection))
                                    <td>
                                        @if($monitor['is_infected'] === true)
                                            @include('status.partials.status-badge', [
                                                'status' => 'down',
                                                'label' => $monitor['infection_label'],
                                            ])
                                        @elseif($monitor['is_infected'] === false)
                                            <span class="status-muted">{{ $monitor['infection_label'] }}</span>
                                        @else
                                            <span class="is-empty">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="status-hide-sm is-num">
                                    @if($monitor['last_checked_at'])
                                        <span title="{{ DisplayDate::format($monitor['last_checked_at'], 'd/m/Y H:i:s') }}">
                                            {{ DisplayDate::parse($monitor['last_checked_at'])?->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="is-empty">—</span>
                                    @endif
                                </td>
                                <td class="status-hide-md is-num">
                                    @if($monitor['last_response_time_ms'])
                                        {{ number_format($monitor['last_response_time_ms'], 0, ',', '.') }} ms
                                    @else
                                        <span class="is-empty">—</span>
                                    @endif
                                </td>
                                <td class="status-hide-lg">
                                    @if($monitor['uptime_percent'] !== null)
                                        <span @class([
                                            'status-uptime',
                                            'status-uptime--ok' => $monitor['uptime_percent'] >= 99,
                                            'status-uptime--warn' => $monitor['uptime_percent'] >= 95 && $monitor['uptime_percent'] < 99,
                                            'status-uptime--down' => $monitor['uptime_percent'] < 95,
                                        ])>{{ number_format($monitor['uptime_percent'], 1, ',', '.') }}%</span>
                                        <span class="status-uptime__sample"> / {{ $monitor['sample_size'] }} check</span>
                                    @else
                                        <span class="is-empty">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('status.monitor', [$status_page['slug'], $monitor['id']]) }}" class="status-action">
                                        Dettaglio
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ !empty($shows_infection) ? 7 : 6 }}" class="is-empty">
                                    {{ !empty($status_filter) ? 'Nessun servizio con questo stato.' : 'Nessun servizio in questa pagina.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="status-footnote">La disponibilità è calcolata sulle ultime 30 esecuzioni per servizio.</p>
        </section>

        @if(count($open_incidents) > 0)
            <section class="status-panel status-panel--wash-down" aria-labelledby="incidenti-heading">
                <div class="status-panel__body">
                    <h2 id="incidenti-heading" class="status-panel__title">Incidenti attivi</h2>
                    <ul class="status-list">
                        @foreach($open_incidents as $incident)
                            <li>
                                <p class="status-list__title">{{ $incident['name'] }}</p>
                                <p class="status-list__copy">{{ $incident['message'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if(count($maintenances) > 0)
            <section class="status-panel status-panel--wash-warn" aria-labelledby="manutenzioni-heading">
                <div class="status-panel__body">
                    <h2 id="manutenzioni-heading" class="status-panel__title">Manutenzioni</h2>
                    <ul class="status-list">
                        @foreach($maintenances as $maintenance)
                            <li>
                                <p class="status-list__title">{{ $maintenance['title'] }}</p>
                                <p class="status-list__copy">{{ $maintenance['message'] }}</p>
                                <p class="status-list__meta">
                                    {{ DisplayDate::format($maintenance['starts_at'], 'd/m/Y H:i') }} -
                                    {{ DisplayDate::format($maintenance['ends_at'], 'd/m/Y H:i') }}
                                    @if($maintenance['is_active']) (in corso) @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if(count($recent_incidents) > 0)
            <section class="status-panel" aria-labelledby="storico-heading">
                <div class="status-panel__body">
                    <h2 id="storico-heading" class="status-panel__title">Storico recente incidenti</h2>
                    <ul class="status-list status-list--ruled">
                        @foreach($recent_incidents as $incident)
                            <li>
                                <div class="status-list__row">
                                    <span class="status-list__title">{{ $incident['name'] }}</span>
                                    <span class="status-muted">{{ $incident['status'] }}</span>
                                </div>
                                <p class="status-list__meta status-muted">
                                    {{ DisplayDate::format($incident['opened_at'], 'd/m/Y H:i') }}
                                    @if($incident['closed_at'])
                                        - {{ DisplayDate::format($incident['closed_at'], 'd/m/Y H:i') }}
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </main>
@endsection
