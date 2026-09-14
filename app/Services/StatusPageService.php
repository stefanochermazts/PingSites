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
            'shows_wordpress_theme' => $statusPage->showsWordpressTheme(),
            'shows_wordpress_version' => $statusPage->showsWordpressVersion(),
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
                    'infection_count' => $statusPage->showsInfectionStatus() ? $monitor->infection_count : null,
                    'infection_db_count' => $statusPage->showsInfectionStatus() ? $monitor->infection_db_count : null,
                    'infection_label' => $statusPage->showsInfectionStatus()
                        ? $this->infectionLabel($monitor->isInfected())
                        : null,
                    'infection_detected_at' => $statusPage->showsInfectionStatus()
                        ? DisplayDate::isoFromModel($monitor, 'infection_detected_at')
                        : null,
                    'wordpress_theme' => $statusPage->showsWordpressTheme() ? $monitor->wordpress_theme : null,
                    'wordpress_theme_slug' => $statusPage->showsWordpressTheme() ? $monitor->wordpress_theme_slug : null,
                    'wordpress_version' => $statusPage->showsWordpressVersion() ? $monitor->wordpress_version : null,
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
                'wordpress_theme' => $statusPage->showsWordpressTheme() ? $monitor->wordpress_theme : null,
                'wordpress_version' => $statusPage->showsWordpressVersion() ? $monitor->wordpress_version : null,
            ],
            'shows_wordpress_theme' => $statusPage->showsWordpressTheme(),
            'shows_wordpress_version' => $statusPage->showsWordpressVersion(),
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
    public function applyStatusFilter(
        array $data,
        ?string $status,
        StatusPage $statusPage,
        ?string $publication = null,
        ?string $sort = null,
        ?string $direction = null,
        ?string $theme = null,
        ?string $version = null,
    ): array {
        $allowedStatus = ['operational', 'down', 'maintenance', 'unknown'];
        if ($statusPage->showsInfectionStatus()) {
            $allowedStatus[] = 'infected';
        }
        $activeStatus = is_string($status) && in_array($status, $allowedStatus, true) ? $status : null;

        $allowedPublication = ['pubblicati', 'non-pubblicati'];
        $activePublication = is_string($publication) && in_array($publication, $allowedPublication, true)
            ? $publication
            : null;

        $activeTheme = $statusPage->showsWordpressTheme() ? $this->normalizeThemeFilter($theme) : null;
        $activeVersion = $statusPage->showsWordpressVersion() ? $this->normalizeVersionFilter($version) : null;

        $allowedSort = ['controllo', 'risposta', 'disponibilita'];
        if ($statusPage->showsInfectionStatus()) {
            $allowedSort[] = 'rilevazione';
        }
        $activeSort = is_string($sort) && in_array($sort, $allowedSort, true) ? $sort : null;
        $activeDirection = $activeSort !== null && $direction === 'asc' ? 'asc' : ($activeSort !== null ? 'desc' : null);

        $query = [
            'status' => $activeStatus,
            'publication' => $activePublication,
            'theme' => $activeTheme,
            'version' => $activeVersion,
            'sort' => $activeSort,
            'direction' => $activeDirection,
        ];

        $monitors = is_array($data['monitors'] ?? null) ? $data['monitors'] : [];

        $data['monitors'] = array_values(array_filter(
            $monitors,
            fn (array $monitor): bool => $this->matchesListingFilters($monitor, $query),
        ));

        $data['status_filter'] = $activeStatus;
        $data['publication_filter'] = $activePublication;
        $data['theme_filter'] = $activeTheme;
        $data['version_filter'] = $activeVersion;
        $data['sort'] = $activeSort;
        $data['sort_direction'] = $activeDirection;
        $data['status_filters'] = $this->statusFilterLinks(
            $statusPage,
            $this->statusCounts($this->monitorsMatching($monitors, $query, except: 'status')),
            $query,
        );
        $data['publication_filters'] = $this->publicationFilterLinks(
            $statusPage,
            $this->publicationCounts($this->monitorsMatching($monitors, $query, except: 'publication')),
            $query,
        );
        $data['theme_filters'] = $statusPage->showsWordpressTheme()
            ? $this->wordpressThemeFilters(
                $statusPage,
                $this->monitorsMatching($monitors, $query, except: 'theme'),
                $query,
            )
            : [];
        $data['version_filters'] = $statusPage->showsWordpressVersion()
            ? $this->wordpressVersionFilters(
                $statusPage,
                $this->monitorsMatching($monitors, $query, except: 'version'),
                $query,
            )
            : [];
        $data['column_sorts'] = $this->columnSorts($statusPage, $query);
        $data['monitors'] = $this->sortMonitors($data['monitors'], $activeSort, $activeDirection);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $monitor
     */
    private function matchesStatus(array $monitor, ?string $status): bool
    {
        if ($status === null) {
            return true;
        }

        if ($status === 'infected') {
            return ($monitor['is_infected'] ?? null) === true;
        }

        return ($monitor['status'] ?? null) === $status;
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
     * @param  array<string, mixed>  $monitor
     * @param  array{status: ?string, publication: ?string, theme: ?string, version: ?string, sort: ?string, direction: ?string}  $query
     */
    private function matchesListingFilters(array $monitor, array $query, ?string $except = null): bool
    {
        if ($except !== 'status' && ! $this->matchesStatus($monitor, $query['status'])) {
            return false;
        }

        if ($except !== 'publication' && ! $this->matchesPublication($monitor, $query['publication'])) {
            return false;
        }

        if ($except !== 'theme' && ! $this->matchesTheme($monitor, $query['theme'])) {
            return false;
        }

        if ($except !== 'version' && ! $this->matchesVersion($monitor, $query['version'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @param  array{status: ?string, publication: ?string, theme: ?string, version: ?string, sort: ?string, direction: ?string}  $query
     * @return list<array<string, mixed>>
     */
    private function monitorsMatching(array $monitors, array $query, ?string $except = null): array
    {
        return array_values(array_filter(
            $monitors,
            fn (array $monitor): bool => $this->matchesListingFilters($monitor, $query, $except),
        ));
    }

    /**
     * @param  array<string, mixed>  $monitor
     */
    private function matchesTheme(array $monitor, ?string $theme): bool
    {
        if ($theme === null) {
            return true;
        }

        $slug = is_string($monitor['wordpress_theme_slug'] ?? null) ? $monitor['wordpress_theme_slug'] : '';

        if ($theme === 'nessuno') {
            return $slug === '';
        }

        return strcasecmp($slug, $theme) === 0;
    }

    /**
     * @param  array<string, mixed>  $monitor
     */
    private function matchesVersion(array $monitor, ?string $version): bool
    {
        if ($version === null) {
            return true;
        }

        $value = is_string($monitor['wordpress_version'] ?? null) ? $monitor['wordpress_version'] : '';

        if ($version === 'nessuno') {
            return $value === '';
        }

        return $value === $version;
    }

    private function normalizeThemeFilter(?string $theme): ?string
    {
        if (! is_string($theme) || $theme === '') {
            return null;
        }

        if ($theme === 'nessuno' || preg_match('/^[A-Za-z0-9._-]+$/', $theme) === 1) {
            return $theme;
        }

        return null;
    }

    private function normalizeVersionFilter(?string $version): ?string
    {
        if (! is_string($version) || $version === '') {
            return null;
        }

        if ($version === 'nessuno' || preg_match('/^\d+\.\d+(?:\.\d+)?$/', $version) === 1) {
            return $version;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @return array{all: int, operational: int, down: int, maintenance: int, unknown: int, infected: int}
     */
    private function statusCounts(array $monitors): array
    {
        $counts = [
            'all' => count($monitors),
            'operational' => 0,
            'down' => 0,
            'maintenance' => 0,
            'unknown' => 0,
            'infected' => 0,
        ];

        foreach ($monitors as $monitor) {
            $key = $monitor['status'] ?? null;
            if (is_string($key) && $key !== 'infected' && array_key_exists($key, $counts)) {
                $counts[$key]++;
            }

            if (($monitor['is_infected'] ?? null) === true) {
                $counts['infected']++;
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
     * @param  array{all: int, operational: int, down: int, maintenance: int, unknown: int, infected: int}  $counts
     * @param  array{status: ?string, publication: ?string, theme: ?string, version: ?string, sort: ?string, direction: ?string}  $query
     * @return list<array{label: string, count: int, active: bool, url: string}>
     */
    private function statusFilterLinks(StatusPage $statusPage, array $counts, array $query): array
    {
        $filters = [
            $this->filterLink($statusPage, 'Tutti gli stati', $counts['all'], $query['status'] === null, ['status' => null] + $query),
            $this->filterLink($statusPage, 'Operativo', $counts['operational'], $query['status'] === 'operational', ['status' => 'operational'] + $query),
            $this->filterLink($statusPage, 'Problemi rilevati', $counts['down'], $query['status'] === 'down', ['status' => 'down'] + $query),
            $this->filterLink($statusPage, 'Manutenzione', $counts['maintenance'], $query['status'] === 'maintenance', ['status' => 'maintenance'] + $query),
            $this->filterLink($statusPage, 'Stato non disponibile', $counts['unknown'], $query['status'] === 'unknown', ['status' => 'unknown'] + $query),
        ];

        if ($statusPage->showsInfectionStatus()) {
            $filters[] = $this->filterLink(
                $statusPage,
                'Infetto',
                $counts['infected'],
                $query['status'] === 'infected',
                ['status' => 'infected'] + $query,
            );
        }

        return $filters;
    }

    /**
     * @param  array{all: int, pubblicati: int, non-pubblicati: int}  $counts
     * @param  array{status: ?string, publication: ?string, theme: ?string, version: ?string, sort: ?string, direction: ?string}  $query
     * @return list<array{label: string, count: int, active: bool, url: string}>
     */
    private function publicationFilterLinks(StatusPage $statusPage, array $counts, array $query): array
    {
        return [
            $this->filterLink($statusPage, 'Tutti i servizi', $counts['all'], $query['publication'] === null, ['publication' => null] + $query),
            $this->filterLink($statusPage, 'Con dominio proprio', $counts['pubblicati'], $query['publication'] === 'pubblicati', ['publication' => 'pubblicati'] + $query),
            $this->filterLink($statusPage, 'Indirizzo temporaneo', $counts['non-pubblicati'], $query['publication'] === 'non-pubblicati', ['publication' => 'non-pubblicati'] + $query),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @param  array{status: ?string, publication: ?string, theme: ?string, version: ?string, sort: ?string, direction: ?string}  $query
     * @return list<array{label: string, count: int, active: bool, url: string}>
     */
    private function wordpressThemeFilters(StatusPage $statusPage, array $monitors, array $query): array
    {
        $groups = [];
        $missing = 0;

        foreach ($monitors as $monitor) {
            $slug = is_string($monitor['wordpress_theme_slug'] ?? null) ? $monitor['wordpress_theme_slug'] : '';
            if ($slug === '') {
                $missing++;

                continue;
            }

            $key = strtolower($slug);
            $label = is_string($monitor['wordpress_theme'] ?? null) && $monitor['wordpress_theme'] !== ''
                ? $monitor['wordpress_theme']
                : $slug;
            $groups[$key] ??= ['slug' => $slug, 'label' => $label, 'count' => 0];
            $groups[$key]['count']++;
        }

        uasort($groups, function (array $left, array $right): int {
            return $right['count'] <=> $left['count']
                ?: strcasecmp($left['label'], $right['label']);
        });

        $filters = [
            $this->filterLink($statusPage, 'Tutti i temi', count($monitors), $query['theme'] === null, ['theme' => null] + $query),
        ];

        foreach ($groups as $group) {
            $filters[] = $this->filterLink(
                $statusPage,
                $group['label'],
                $group['count'],
                is_string($query['theme']) && strcasecmp($query['theme'], $group['slug']) === 0,
                ['theme' => $group['slug']] + $query,
            );
        }

        if ($missing > 0) {
            $filters[] = $this->filterLink(
                $statusPage,
                'Senza tema',
                $missing,
                $query['theme'] === 'nessuno',
                ['theme' => 'nessuno'] + $query,
            );
        }

        return $filters;
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @param  array{status: ?string, publication: ?string, theme: ?string, version: ?string, sort: ?string, direction: ?string}  $query
     * @return list<array{label: string, count: int, active: bool, url: string}>
     */
    private function wordpressVersionFilters(StatusPage $statusPage, array $monitors, array $query): array
    {
        $groups = [];
        $missing = 0;

        foreach ($monitors as $monitor) {
            $version = is_string($monitor['wordpress_version'] ?? null) ? $monitor['wordpress_version'] : '';
            if ($version === '') {
                $missing++;

                continue;
            }

            $groups[$version] ??= ['label' => $version, 'count' => 0];
            $groups[$version]['count']++;
        }

        uksort($groups, fn (string $left, string $right): int => version_compare($right, $left));

        $filters = [
            $this->filterLink($statusPage, 'Tutte le versioni', count($monitors), $query['version'] === null, ['version' => null] + $query),
        ];

        foreach ($groups as $version => $group) {
            $filters[] = $this->filterLink(
                $statusPage,
                $group['label'],
                $group['count'],
                $query['version'] === $version,
                ['version' => $version] + $query,
            );
        }

        if ($missing > 0) {
            $filters[] = $this->filterLink(
                $statusPage,
                'Senza versione',
                $missing,
                $query['version'] === 'nessuno',
                ['version' => 'nessuno'] + $query,
            );
        }

        return $filters;
    }

    /**
     * @param  array{status?: ?string, publication?: ?string, theme?: ?string, version?: ?string, sort?: ?string, direction?: ?string}  $query
     * @return array{label: string, count: int, active: bool, url: string}
     */
    private function filterLink(
        StatusPage $statusPage,
        string $label,
        int $count,
        bool $active,
        array $query,
    ): array {
        return [
            'label' => $label,
            'count' => $count,
            'active' => $active,
            'url' => $this->listingUrl($statusPage, $query),
        ];
    }

    /**
     * @param  array{status: ?string, publication: ?string, theme: ?string, version: ?string, sort: ?string, direction: ?string}  $query
     * @return array<string, array{label: string, url: string, active: bool, aria_sort: string}>
     */
    private function columnSorts(StatusPage $statusPage, array $query): array
    {
        $columns = [
            'controllo' => 'Ultimo controllo',
            'risposta' => 'Risposta',
            'disponibilita' => 'Disponibilità',
        ];

        if ($statusPage->showsInfectionStatus()) {
            $columns = ['rilevazione' => 'Rilevata'] + $columns;
        }

        $headers = [];
        foreach ($columns as $key => $label) {
            $active = $query['sort'] === $key;
            $nextDirection = $active && $query['direction'] === 'desc' ? 'asc' : 'desc';

            $headers[$key] = [
                'label' => $label,
                'url' => $this->listingUrl($statusPage, ['sort' => $key, 'direction' => $nextDirection] + $query),
                'active' => $active,
                'aria_sort' => $active
                    ? ($query['direction'] === 'asc' ? 'ascending' : 'descending')
                    : 'none',
            ];
        }

        return $headers;
    }

    /**
     * @param  list<array<string, mixed>>  $monitors
     * @return list<array<string, mixed>>
     */
    private function sortMonitors(array $monitors, ?string $sort, ?string $direction): array
    {
        if ($sort === null || $direction === null) {
            return $monitors;
        }

        usort($monitors, function (array $left, array $right) use ($sort, $direction): int {
            $leftValue = $this->sortValue($left, $sort);
            $rightValue = $this->sortValue($right, $sort);

            if ($leftValue === null && $rightValue === null) {
                return 0;
            }
            if ($leftValue === null) {
                return 1;
            }
            if ($rightValue === null) {
                return -1;
            }

            $comparison = $leftValue <=> $rightValue;

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return $monitors;
    }

    private function sortValue(array $monitor, string $sort): int|float|null
    {
        return match ($sort) {
            'controllo' => $this->timestampValue($monitor['last_checked_at'] ?? null),
            'rilevazione' => $this->timestampValue($monitor['infection_detected_at'] ?? null),
            'risposta' => is_numeric($monitor['last_response_time_ms'] ?? null)
                ? (float) $monitor['last_response_time_ms']
                : null,
            'disponibilita' => is_numeric($monitor['uptime_percent'] ?? null)
                ? (float) $monitor['uptime_percent']
                : null,
            default => null,
        };
    }

    private function timestampValue(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @param  array{status?: ?string, publication?: ?string, theme?: ?string, version?: ?string, sort?: ?string, direction?: ?string}  $query
     */
    private function listingUrl(StatusPage $statusPage, array $query): string
    {
        $params = ['statusPage' => $statusPage];
        $map = [
            'status' => 'status',
            'publication' => 'pubblicazione',
            'theme' => 'tema',
            'version' => 'versione',
        ];

        foreach ($map as $key => $param) {
            if (isset($query[$key]) && is_string($query[$key]) && $query[$key] !== '') {
                $params[$param] = $query[$key];
            }
        }

        if (isset($query['sort']) && is_string($query['sort']) && $query['sort'] !== '') {
            $params['ordina'] = $query['sort'];
            $params['dir'] = $query['direction'] ?? 'desc';
        }

        return route('status.show', $params);
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
