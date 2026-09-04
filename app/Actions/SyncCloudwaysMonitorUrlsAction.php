<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\Cloudways\CloudwaysAppUrl;
use App\Services\Cloudways\CloudwaysClient;
use App\Support\SsrfGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SyncCloudwaysMonitorUrlsAction
{
    public function __construct(
        private readonly CloudwaysClient $client,
        private readonly ImportCloudwaysAppsAction $import,
        private readonly SsrfGuard $ssrfGuard,
    ) {}

    /**
     * @return array{updated: int, unchanged: int, missing: int, failed: int, created: int, linked: int, removed: int}
     */
    public function handle(?string $accessToken = null): array
    {
        $monitors = Monitor::query()
            ->whereNotNull('cloudways_server_id')
            ->whereNotNull('cloudways_app_id')
            ->get();

        $result = [
            'updated' => 0,
            'unchanged' => 0,
            'missing' => 0,
            'failed' => 0,
            'created' => 0,
            'linked' => 0,
            'removed' => 0,
        ];

        if ($monitors->isEmpty()) {
            return $result;
        }

        $index = $this->client->appIndex($accessToken);

        foreach ($monitors as $monitor) {
            $key = $monitor->cloudways_server_id.':'.$monitor->cloudways_app_id;
            $app = $index[$key] ?? null;

            if (! is_array($app)) {
                $result['missing']++;

                continue;
            }

            $url = CloudwaysAppUrl::fromApp($app);
            if ($url === null) {
                $result['failed']++;

                continue;
            }

            try {
                $this->ssrfGuard->validateUrl($url);
            } catch (InvalidArgumentException $exception) {
                Log::warning('URL Cloudways non valido, monitor non aggiornato', [
                    'monitor_id' => $monitor->id,
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);
                $result['failed']++;

                continue;
            }

            $duplicate = $this->monitorWithUrl($url, $monitor->id);
            if ($duplicate !== null) {
                $this->mergeInto($duplicate, $monitor);
                $result['removed']++;

                continue;
            }

            if ($monitor->url === $url) {
                $result['unchanged']++;

                continue;
            }

            $previousUrl = $monitor->url;
            $monitor->url = $url;
            $monitor->save();

            Log::info('URL monitor aggiornato da Cloudways', [
                'monitor_id' => $monitor->id,
                'from' => $previousUrl,
                'to' => $url,
            ]);

            $result['updated']++;
        }

        $result['removed'] += $this->removeReplacedTemporaryUrls($index);

        $imported = $this->importNewApps(
            Monitor::query()
                ->whereNotNull('cloudways_server_id')
                ->whereNotNull('cloudways_app_id')
                ->get(),
            $index,
        );
        $result['created'] += $imported['created'];
        $result['linked'] += $imported['linked'];
        $result['failed'] += $imported['failed'];

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     */
    private function removeReplacedTemporaryUrls(array $index): int
    {
        $removed = 0;

        foreach ($index as $app) {
            $canonical = CloudwaysAppUrl::fromApp($app);
            $temporary = CloudwaysAppUrl::temporaryUrl($app);

            if ($canonical === null || $temporary === null || $canonical === $temporary) {
                continue;
            }

            $keeper = $this->monitorWithUrl($canonical);
            if ($keeper === null) {
                continue;
            }

            foreach ($this->monitorsWithUrl($temporary) as $stale) {
                if ($stale->id === $keeper->id) {
                    continue;
                }

                $this->mergeInto($keeper, $stale);
                $removed++;
            }
        }

        return $removed;
    }

    private function mergeInto(Monitor $keeper, Monitor $stale): void
    {
        if (! filled($keeper->cloudways_server_id) || ! filled($keeper->cloudways_app_id)) {
            $keeper->cloudways_server_id = $stale->cloudways_server_id;
            $keeper->cloudways_app_id = $stale->cloudways_app_id;
        }

        $stale->cloudways_server_id = null;
        $stale->cloudways_app_id = null;
        $stale->save();

        if ($keeper->isDirty()) {
            $keeper->save();
        }

        Log::info('Monitor Cloudways temporaneo rimosso dopo il passaggio al dominio proprio', [
            'kept_monitor_id' => $keeper->id,
            'removed_monitor_id' => $stale->id,
            'url' => $keeper->url,
        ]);

        $stale->delete();
    }

    private function monitorWithUrl(string $url, ?int $exceptId = null): ?Monitor
    {
        return $this->monitorsWithUrl($url, $exceptId)->first();
    }

    /**
     * @return Collection<int, Monitor>
     */
    private function monitorsWithUrl(string $url, ?int $exceptId = null): Collection
    {
        return Monitor::query()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereIn('url', [$url, $url.'/'])
            ->get();
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @param  array<string, array<string, mixed>>  $index
     * @return array{created: int, linked: int, failed: int}
     */
    private function importNewApps(Collection $monitors, array $index): array
    {
        $imported = [
            'created' => 0,
            'linked' => 0,
            'failed' => 0,
        ];

        $monitorsByServer = $monitors->groupBy('cloudways_server_id');

        foreach ($monitorsByServer as $serverId => $serverMonitors) {
            $statusPage = $this->statusPageForServer($serverMonitors);
            if ($statusPage === null) {
                continue;
            }

            $apps = [];
            $prefix = $serverId.':';

            foreach ($index as $key => $app) {
                if (str_starts_with($key, $prefix)) {
                    $apps[] = $app;
                }
            }

            if ($apps === []) {
                continue;
            }

            $result = $this->import->importApps((string) $serverId, $statusPage, $apps);
            $imported['created'] += $result['created'];
            $imported['linked'] += $result['linked'];
            $imported['failed'] += $result['failed'];
        }

        return $imported;
    }

    /**
     * @param  Collection<int, Monitor>  $serverMonitors
     */
    private function statusPageForServer(Collection $serverMonitors): ?StatusPage
    {
        $statusPageId = $serverMonitors
            ->pluck('status_page_id')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        if ($statusPageId === null) {
            return null;
        }

        return StatusPage::query()->find((int) $statusPageId);
    }
}
