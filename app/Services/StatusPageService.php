<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Check;
use App\Models\Incident;
use App\Models\Monitor;
use App\Settings\MonitorSettings;
use Illuminate\Support\Collection;

class StatusPageService
{
    private const RECENT_CHECKS_LIMIT = 30;

    public function __construct(
        private readonly MonitorSettings $settings,
        private readonly MaintenanceService $maintenanceService,
    ) {}

    public function data(): array
    {
        $monitors = Monitor::query()
            ->where('published', true)
            ->orderBy('public_name')
            ->orderBy('name')
            ->get();

        $recentChecksByMonitor = $this->recentChecksForMonitors($monitors->pluck('id'));

        $openIncidents = Incident::query()
            ->where('status', IncidentStatus::Open)
            ->where('public_visible', true)
            ->whereHas('monitor', fn ($query) => $query->where('published', true))
            ->with('monitor')
            ->orderByDesc('opened_at')
            ->get();

        $maintenances = $this->maintenanceService->publicActiveOrUpcoming();

        $recentIncidents = Incident::query()
            ->where('public_visible', true)
            ->whereHas('monitor', fn ($query) => $query->where('published', true))
            ->where('opened_at', '>=', now()->subDays(30))
            ->with('monitor')
            ->orderByDesc('opened_at')
            ->limit(10)
            ->get();

        return [
            'title' => $this->settings->status_page_title,
            'overall_status' => $this->overallStatus($monitors, $maintenances),
            'overall_status_label' => $this->overallStatusLabel($monitors, $maintenances),
            'monitors' => $monitors->map(function (Monitor $monitor) use ($recentChecksByMonitor) {
                $checks = $recentChecksByMonitor->get($monitor->id, collect());
                $stats = $this->checkStats($checks);

                return [
                    'id' => $monitor->id,
                    'name' => $monitor->displayPublicName(),
                    'status' => $this->publicMonitorStatus($monitor),
                    'status_label' => $this->publicMonitorStatusLabel($monitor),
                    'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
                    'last_response_time_ms' => $monitor->last_response_time_ms,
                    'uptime_percent' => $stats['uptime_percent'],
                    'avg_response_time_ms' => $stats['avg_response_time_ms'],
                    'sample_size' => $stats['sample_size'],
                ];
            })->values()->all(),
            'open_incidents' => $openIncidents->map(fn (Incident $incident) => [
                'name' => $incident->monitor->displayPublicName(),
                'message' => $incident->publicMessage(),
                'opened_at' => $incident->opened_at->toIso8601String(),
            ])->values()->all(),
            'maintenances' => $maintenances->map(fn ($maintenance) => [
                'title' => $maintenance->title,
                'message' => $maintenance->public_message ?: 'Manutenzione programmata.',
                'starts_at' => $maintenance->starts_at->toIso8601String(),
                'ends_at' => $maintenance->ends_at->toIso8601String(),
                'is_active' => $maintenance->isActive(),
            ])->values()->all(),
            'recent_incidents' => $recentIncidents->map(fn (Incident $incident) => [
                'name' => $incident->monitor->displayPublicName(),
                'status' => $incident->status->label(),
                'opened_at' => $incident->opened_at->toIso8601String(),
                'closed_at' => $incident->closed_at?->toIso8601String(),
            ])->values()->all(),
            'updated_at' => Monitor::query()->where('published', true)->max('last_checked_at'),
        ];
    }

    public function monitorDetail(Monitor $monitor): array
    {
        abort_unless($monitor->published, 404);

        $checks = Check::query()
            ->where('monitor_id', $monitor->id)
            ->orderByDesc('checked_at')
            ->limit(self::RECENT_CHECKS_LIMIT)
            ->get();

        $stats = $this->checkStats($checks);
        $chartChecks = $checks->reverse()->values();

        return [
            'title' => $this->settings->status_page_title,
            'monitor' => [
                'id' => $monitor->id,
                'name' => $monitor->displayPublicName(),
                'status' => $this->publicMonitorStatus($monitor),
                'status_label' => $this->publicMonitorStatusLabel($monitor),
                'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
                'last_response_time_ms' => $monitor->last_response_time_ms,
            ],
            'stats' => $stats,
            'checks' => $checks->map(fn (Check $check) => $this->publicCheckPayload($check))->values()->all(),
            'chart' => [
                'labels' => $chartChecks->map(fn (Check $check) => $check->checked_at->format('d/m H:i'))->all(),
                'response_times' => $chartChecks->map(fn (Check $check) => $check->response_time_ms)->all(),
                'success' => $chartChecks->map(fn (Check $check) => $check->success)->all(),
            ],
        ];
    }

    public static function cacheKey(): string
    {
        return 'status-page';
    }

    public static function monitorCacheKey(Monitor $monitor): string
    {
        return 'status-page-monitor-'.$monitor->id;
    }

    public static function forgetCache(?Monitor $monitor = null): void
    {
        cache()->forget(self::cacheKey());

        if ($monitor) {
            cache()->forget(self::monitorCacheKey($monitor));
        }
    }

    /**
     * @param  Collection<int, int>  $monitorIds
     * @return Collection<int, Collection<int, Check>>
     */
    private function recentChecksForMonitors(Collection $monitorIds): Collection
    {
        if ($monitorIds->isEmpty()) {
            return collect();
        }

        return Check::query()
            ->whereIn('monitor_id', $monitorIds)
            ->orderByDesc('checked_at')
            ->get()
            ->groupBy('monitor_id')
            ->map(fn (Collection $checks) => $checks->take(self::RECENT_CHECKS_LIMIT)->values());
    }

    /**
     * @param  Collection<int, Check>  $checks
     * @return array{uptime_percent: ?float, avg_response_time_ms: ?int, sample_size: int}
     */
    private function checkStats(Collection $checks): array
    {
        if ($checks->isEmpty()) {
            return [
                'uptime_percent' => null,
                'avg_response_time_ms' => null,
                'sample_size' => 0,
            ];
        }

        $successful = $checks->where('success', true);

        return [
            'uptime_percent' => round($successful->count() / $checks->count() * 100, 1),
            'avg_response_time_ms' => $successful->isNotEmpty()
                ? (int) round($successful->avg('response_time_ms'))
                : null,
            'sample_size' => $checks->count(),
        ];
    }

    /**
     * @return array{checked_at: string, success: bool, response_time_ms: ?int, status_label: string}
     */
    private function publicCheckPayload(Check $check): array
    {
        return [
            'checked_at' => $check->checked_at->toIso8601String(),
            'success' => $check->success,
            'response_time_ms' => $check->response_time_ms,
            'status_label' => $check->success ? 'Operativo' : 'Non disponibile',
        ];
    }

    private function overallStatus(Collection $monitors, Collection $maintenances): string
    {
        if ($monitors->isEmpty()) {
            return 'unavailable';
        }

        if ($monitors->contains(fn (Monitor $m) => $this->publicMonitorStatus($m) === 'down')) {
            return 'degraded';
        }

        if ($maintenances->contains(fn ($m) => $m->isActive()) ||
            $monitors->contains(fn (Monitor $m) => $this->publicMonitorStatus($m) === 'maintenance')) {
            return 'maintenance';
        }

        return 'operational';
    }

    private function overallStatusLabel(Collection $monitors, Collection $maintenances): string
    {
        return match ($this->overallStatus($monitors, $maintenances)) {
            'degraded' => 'Problemi su uno o più servizi',
            'maintenance' => 'Manutenzione in corso',
            'operational' => 'Tutti i servizi operativi',
            default => 'Stato non disponibile',
        };
    }

    private function publicMonitorStatus(Monitor $monitor): string
    {
        if ($this->maintenanceService->isMonitorInMaintenance($monitor)) {
            return 'maintenance';
        }

        return match ($monitor->status) {
            MonitorStatus::Down => 'down',
            MonitorStatus::Maintenance => 'maintenance',
            MonitorStatus::Online => 'operational',
            default => 'unknown',
        };
    }

    private function publicMonitorStatusLabel(Monitor $monitor): string
    {
        return match ($this->publicMonitorStatus($monitor)) {
            'down' => 'Problemi rilevati',
            'maintenance' => 'Manutenzione',
            'operational' => 'Operativo',
            default => 'Stato non disponibile',
        };
    }
}
