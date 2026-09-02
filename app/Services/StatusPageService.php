<?php

namespace App\Services;

use App\Enums\ErrorType;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Check;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\Cloudways\CloudwaysAppUrl;
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
            'shows_infection' => $statusPage->showsInfectionStatus(),
            'monitors' => $monitors->map(function (Monitor $monitor) use ($recentChecksByMonitor, $statusPage) {
                $checks = $recentChecksByMonitor->get($monitor->id, collect());
                $stats = $this->checkStats($checks);

                return [
                    'id' => $monitor->id,
                    'name' => $monitor->displayPublicName(),
                    'url' => $monitor->url,
                    'status' => $this->publicMonitorStatus($monitor),
                    'status_label' => $this->publicMonitorStatusLabel($monitor),
                    'error_detail' => $this->publicErrorDetail($monitor),
                    'is_infected' => $statusPage->showsInfectionStatus() ? $monitor->isInfected() : null,
                    'infection_label' => $statusPage->showsInfectionStatus()
                        ? $this->infectionLabel($monitor->isInfected())
                        : null,
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
                'url' => $monitor->url,
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyStatusFilter(array $data, ?string $status, StatusPage $statusPage, ?string $publication = null): array
    {
        $allowedStatus = ['operational', 'down', 'maintenance', 'unknown'];
        $activeStatus = is_string($status) && in_array($status, $allowedStatus, true) ? $status : null;

        $allowedPublication = ['pubblicati', 'non-pubblicati'];
        $activePublication = is_string($publication) && in_array($publication, $allowedPublication, true)
            ? $publication
            : null;

        $monitors = is_array($data['monitors'] ?? null) ? $data['monitors'] : [];

        $matchingStatus = array_values(array_filter(
            $monitors,
            fn (array $monitor): bool => $activeStatus === null || ($monitor['status'] ?? null) === $activeStatus,
        ));
        $matchingPublication = array_values(array_filter(
            $monitors,
            fn (array $monitor): bool => $this->matchesPublication($monitor, $activePublication),
        ));

        $data['monitors'] = array_values(array_filter(
            $matchingPublication,
            fn (array $monitor): bool => $activeStatus === null || ($monitor['status'] ?? null) === $activeStatus,
        ));

        $statusCounts = $this->statusCounts($matchingPublication);
        $publicationCounts = $this->publicationCounts($matchingStatus);

        $data['status_filter'] = $activeStatus;
        $data['publication_filter'] = $activePublication;
        $data['status_filters'] = [
            $this->filterLink($statusPage, 'Tutti gli stati', $statusCounts['all'], $activeStatus === null, null, $activePublication),
            $this->filterLink($statusPage, 'Operativo', $statusCounts['operational'], $activeStatus === 'operational', 'operational', $activePublication),
            $this->filterLink($statusPage, 'Problemi rilevati', $statusCounts['down'], $activeStatus === 'down', 'down', $activePublication),
            $this->filterLink($statusPage, 'Manutenzione', $statusCounts['maintenance'], $activeStatus === 'maintenance', 'maintenance', $activePublication),
            $this->filterLink($statusPage, 'Stato non disponibile', $statusCounts['unknown'], $activeStatus === 'unknown', 'unknown', $activePublication),
        ];
        $data['publication_filters'] = [
            $this->filterLink($statusPage, 'Tutti i servizi', $publicationCounts['all'], $activePublication === null, $activeStatus, null),
            $this->filterLink($statusPage, 'Con dominio proprio', $publicationCounts['pubblicati'], $activePublication === 'pubblicati', $activeStatus, 'pubblicati'),
            $this->filterLink($statusPage, 'Indirizzo temporaneo', $publicationCounts['non-pubblicati'], $activePublication === 'non-pubblicati', $activeStatus, 'non-pubblicati'),
        ];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $monitor
     */
    private function matchesPublication(array $monitor, ?string $publication): bool
    {
        if ($publication === null) {
            return true;
        }

        $url = is_string($monitor['url'] ?? null) ? $monitor['url'] : null;
        $isTemporary = CloudwaysAppUrl::isTemporaryCloudwaysUrl($url);

        return $publication === 'non-pubblicati' ? $isTemporary : ! $isTemporary;
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @return array{all: int, operational: int, down: int, maintenance: int, unknown: int}
     */
    private function statusCounts(array $monitors): array
    {
        $counts = [
            'all' => count($monitors),
            'operational' => 0,
            'down' => 0,
            'maintenance' => 0,
            'unknown' => 0,
        ];

        foreach ($monitors as $monitor) {
            $key = $monitor['status'] ?? null;
            if (is_string($key) && array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @return array{all: int, pubblicati: int, non-pubblicati: int}
     */
    private function publicationCounts(array $monitors): array
    {
        $counts = [
            'all' => count($monitors),
            'pubblicati' => 0,
            'non-pubblicati' => 0,
        ];

        foreach ($monitors as $monitor) {
            $url = is_string($monitor['url'] ?? null) ? $monitor['url'] : null;
            if (CloudwaysAppUrl::isTemporaryCloudwaysUrl($url)) {
                $counts['non-pubblicati']++;
            } else {
                $counts['pubblicati']++;
            }
        }

        return $counts;
    }

    /**
     * @return array{label: string, count: int, active: bool, url: string}
     */
    private function filterLink(
        StatusPage $statusPage,
        string $label,
        int $count,
        bool $active,
        ?string $status,
        ?string $publication,
    ): array {
        $params = ['statusPage' => $statusPage];
        if ($status !== null) {
            $params['status'] = $status;
        }
        if ($publication !== null) {
            $params['pubblicazione'] = $publication;
        }

        return [
            'label' => $label,
            'count' => $count,
            'active' => $active,
            'url' => route('status.show', $params),
        ];
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

    private function infectionLabel(?bool $infected): string
    {
        return match ($infected) {
            true => 'Infetto',
            false => 'Pulito',
            default => 'Non verificato',
        };
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
            MonitorStatus::Down => $this->inferredPublicStatus($monitor) === 'operational'
                ? 'operational'
                : 'down',
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

    private function publicErrorDetail(Monitor $monitor): ?string
    {
        $status = $this->publicMonitorStatus($monitor);
        if (! in_array($status, ['down', 'unknown'], true)) {
            return null;
        }

        if (! $monitor->last_error_type instanceof ErrorType) {
            return null;
        }

        $parts = [];

        if ($monitor->last_http_code) {
            $parts[] = 'HTTP '.$monitor->last_http_code;
        }

        $redundantHttpFamily = $monitor->last_http_code !== null
            && in_array($monitor->last_error_type, [
                ErrorType::Http4xx,
                ErrorType::Http5xx,
            ], true);

        if (! $redundantHttpFamily) {
            $parts[] = $monitor->last_error_type->label();
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
