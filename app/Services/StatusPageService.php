<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Check;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Support\DisplayDate;
use Illuminate\Support\Collection;

class StatusPageService
{
    private const RECENT_CHECKS_LIMIT = 30;

    public function __construct(
        private readonly MaintenanceService $maintenanceService,
    ) {}

    public function defaultPage(): StatusPage
    {
        return StatusPage::query()
            ->where('is_default', true)
            ->firstOrFail();
    }

    public function data(StatusPage $statusPage): array
    {
        $monitors = $this->publishedMonitorsForPage($statusPage);

        $recentChecksByMonitor = $this->recentChecksForMonitors($monitors->pluck('id'));
        $monitorIds = $monitors->pluck('id');

        $openIncidents = Incident::query()
            ->where('status', IncidentStatus::Open)
            ->where('public_visible', true)
            ->whereHas('monitor', fn ($query) => $query
                ->where('published', true)
                ->where('status_page_id', $statusPage->id))
            ->with('monitor')
            ->orderByDesc('opened_at')
            ->get();

        $maintenances = $this->maintenanceService->publicActiveOrUpcomingForMonitors($monitorIds);

        $recentIncidents = Incident::query()
            ->where('public_visible', true)
            ->whereHas('monitor', fn ($query) => $query
                ->where('published', true)
                ->where('status_page_id', $statusPage->id))
            ->where('opened_at', '>=', now()->subDays(30))
            ->with('monitor')
            ->orderByDesc('opened_at')
            ->limit(10)
            ->get();

        return [
            'status_page' => [
                'slug' => $statusPage->slug,
                'name' => $statusPage->name,
            ],
            'title' => $statusPage->title,
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
                    'last_checked_at' => DisplayDate::isoFromModel($monitor, 'last_checked_at'),
                    'last_response_time_ms' => $monitor->last_response_time_ms,
                    'uptime_percent' => $stats['uptime_percent'],
                    'avg_response_time_ms' => $stats['avg_response_time_ms'],
                    'sample_size' => $stats['sample_size'],
                ];
            })->values()->all(),
            'open_incidents' => $openIncidents->map(fn (Incident $incident) => [
                'name' => $incident->monitor->displayPublicName(),
                'message' => $incident->publicMessage(),
                'opened_at' => DisplayDate::isoFromModel($incident, 'opened_at'),
            ])->values()->all(),
            'maintenances' => $maintenances->map(fn ($maintenance) => [
                'title' => $maintenance->title,
                'message' => $maintenance->public_message ?: 'Manutenzione programmata.',
                'starts_at' => DisplayDate::isoFromModel($maintenance, 'starts_at'),
                'ends_at' => DisplayDate::isoFromModel($maintenance, 'ends_at'),
                'is_active' => $maintenance->isActive(),
            ])->values()->all(),
            'recent_incidents' => $recentIncidents->map(fn (Incident $incident) => [
                'name' => $incident->monitor->displayPublicName(),
                'status' => $incident->status->label(),
                'opened_at' => DisplayDate::isoFromModel($incident, 'opened_at'),
                'closed_at' => DisplayDate::isoFromModel($incident, 'closed_at'),
            ])->values()->all(),
            'updated_at' => $monitors
                ->filter(fn (Monitor $monitor) => $monitor->getRawOriginal('last_checked_at') !== null)
                ->map(fn (Monitor $monitor) => DisplayDate::isoFromModel($monitor, 'last_checked_at'))
                ->max(),
        ];
    }

    public function monitorDetail(StatusPage $statusPage, Monitor $monitor): array
    {
        abort_unless($this->monitorBelongsToPage($monitor, $statusPage), 404);

        $checks = Check::query()
            ->where('monitor_id', $monitor->id)
            ->orderByDesc('checked_at')
            ->limit(self::RECENT_CHECKS_LIMIT)
            ->get();

        $stats = $this->checkStats($checks);
        $chartChecks = $checks->reverse()->values();

        return [
            'status_page' => [
                'slug' => $statusPage->slug,
                'name' => $statusPage->name,
            ],
            'title' => $statusPage->title,
            'monitor' => [
                'id' => $monitor->id,
                'name' => $monitor->displayPublicName(),
                'status' => $this->publicMonitorStatus($monitor),
                'status_label' => $this->publicMonitorStatusLabel($monitor),
                'last_checked_at' => DisplayDate::isoFromModel($monitor, 'last_checked_at'),
                'last_response_time_ms' => $monitor->last_response_time_ms,
            ],
            'stats' => $stats,
            'checks' => $checks->map(fn (Check $check) => $this->publicCheckPayload($check))->values()->all(),
            'chart' => [
                'labels' => $chartChecks->map(fn (Check $check) => DisplayDate::format(
                    $check->checked_at,
                    'd/m H:i',
                ))->all(),
                'response_times' => $chartChecks->map(fn (Check $check) => $check->response_time_ms)->all(),
                'success' => $chartChecks->map(fn (Check $check) => $check->success)->all(),
            ],
        ];
    }

    public function monitorBelongsToPage(Monitor $monitor, StatusPage $statusPage): bool
    {
        return $monitor->published
            && $monitor->status_page_id === $statusPage->id;
    }

    public static function cacheKey(StatusPage $statusPage): string
    {
        return 'status-page-'.$statusPage->slug;
    }

    public static function monitorCacheKey(StatusPage $statusPage, Monitor $monitor): string
    {
        return 'status-page-'.$statusPage->slug.'-monitor-'.$monitor->id;
    }

    public static function forgetAllCaches(?Monitor $monitor = null): void
    {
        StatusPage::query()->each(function (StatusPage $statusPage) use ($monitor): void {
            cache()->forget(self::cacheKey($statusPage));

            if ($monitor) {
                cache()->forget(self::monitorCacheKey($statusPage, $monitor));
            }
        });
    }

    /**
     * @return Collection<int, Monitor>
     */
    private function publishedMonitorsForPage(StatusPage $statusPage): Collection
    {
        return Monitor::query()
            ->where('published', true)
            ->where('status_page_id', $statusPage->id)
            ->orderBy('public_name')
            ->orderBy('name')
            ->get();
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
            'checked_at' => DisplayDate::isoFromModel($check, 'checked_at'),
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
            MonitorStatus::Unknown, MonitorStatus::Paused => $this->inferredPublicStatus($monitor),
        };
    }

    private function inferredPublicStatus(Monitor $monitor): string
    {
        if ($monitor->last_checked_at && $monitor->last_error_type === null) {
            return 'operational';
        }

        return 'unknown';
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
