<?php

namespace App\Filament\Widgets;

use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Check;
use App\Models\Incident;
use App\Models\Monitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class MonitorStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $total = Monitor::query()->count();
        $online = Monitor::query()->where('status', MonitorStatus::Online)->count();
        $down = Monitor::query()->where('status', MonitorStatus::Down)->count();
        $maintenance = Monitor::query()->where('status', MonitorStatus::Maintenance)->count();
        $paused = Monitor::query()->where('status', MonitorStatus::Paused)->count();
        $openIncidents = Incident::query()->where('status', IncidentStatus::Open)->count();
        $lastCheck = Check::query()->max('checked_at');

        return [
            Stat::make('Monitor totali', (string) $total),
            Stat::make('Online', (string) $online)->color('success'),
            Stat::make('Down', (string) $down)->color('danger'),
            Stat::make('Manutenzione', (string) $maintenance)->color('warning'),
            Stat::make('Sospesi', (string) $paused),
            Stat::make('Incidenti aperti', (string) $openIncidents)->color($openIncidents > 0 ? 'danger' : 'success'),
            Stat::make('Ultimo controllo', $lastCheck ? Carbon::parse($lastCheck)->format('d/m/Y H:i') : 'N/D'),
        ];
    }
}
