<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Services\Cloudways\CloudwaysAppUrl;
use App\Services\Cloudways\CloudwaysClient;
use App\Support\SsrfGuard;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SyncCloudwaysMonitorUrlsAction
{
    public function __construct(
        private readonly CloudwaysClient $client,
        private readonly SsrfGuard $ssrfGuard,
    ) {}

    /**
     * @return array{updated: int, unchanged: int, missing: int, failed: int}
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

        return $result;
    }
}
