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
     * @return array{updated: int, unchanged: int, missing: int, failed: int, created: int, linked: int}
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

            if ($monitor->url === $url) {
                $result['unchanged']++;

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

        $imported = $this->importNewApps($monitors, $index);
        $result['created'] += $imported['created'];
        $result['linked'] += $imported['linked'];
        $result['failed'] += $imported['failed'];

        return $result;
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
