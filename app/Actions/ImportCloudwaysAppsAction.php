<?php

namespace App\Actions;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\Cloudways\CloudwaysAppUrl;
use App\Services\Cloudways\CloudwaysClient;
use App\Services\Cloudways\CloudwaysException;
use App\Settings\MonitorSettings;
use App\Support\SsrfGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportCloudwaysAppsAction
{
    public function __construct(
        private readonly CloudwaysClient $client,
        private readonly MonitorSettings $settings,
        private readonly SsrfGuard $ssrfGuard,
    ) {}

    /**
     * @return array{created: int, linked: int, skipped: int, failed: int}
     */
    public function handle(string $serverId, int $statusPageId, ?string $accessToken = null): array
    {
        $statusPage = StatusPage::query()->find($statusPageId);
        if ($statusPage === null) {
            throw new CloudwaysException('Status page non trovata.');
        }

        $apps = $this->client->appsForServer($serverId, $accessToken);

        $result = [
            'created' => 0,
            'linked' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        DB::transaction(function () use ($apps, $serverId, $statusPage, &$result): void {
            foreach ($apps as $app) {
                if (! is_array($app)) {
                    $result['failed']++;

                    continue;
                }

                $outcome = $this->importApp($app, $serverId, $statusPage);
                $result[$outcome]++;
            }
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $app
     * @return 'created'|'linked'|'skipped'|'failed'
     */
    private function importApp(array $app, string $serverId, StatusPage $statusPage): string
    {
        $appId = CloudwaysAppUrl::stringValue($app['id'] ?? null);
        $name = CloudwaysAppUrl::stringValue($app['label'] ?? null);
        $url = CloudwaysAppUrl::fromApp($app);

        if ($appId === '' || $name === '' || $url === null) {
            return 'failed';
        }

        try {
            $this->ssrfGuard->validateUrl($url);
        } catch (InvalidArgumentException) {
            return 'failed';
        }

        $existingById = Monitor::query()
            ->where('cloudways_server_id', $serverId)
            ->where('cloudways_app_id', $appId)
            ->first();

        if ($existingById !== null) {
            return 'skipped';
        }

        $existingByUrl = Monitor::query()->whereIn('url', [$url, $url.'/'])->first();
        if ($existingByUrl !== null) {
            $existingByUrl->fill([
                'cloudways_server_id' => $serverId,
                'cloudways_app_id' => $appId,
            ]);
            $existingByUrl->save();

            return 'linked';
        }

        $monitor = new Monitor([
            'name' => $name,
            'public_name' => $name,
            'url' => $url,
            'status' => MonitorStatus::Unknown,
            'is_active' => true,
            'check_frequency' => 10,
            'timeout' => 10,
            'valid_status_codes' => array_map('intval', $this->settings->default_valid_status_codes),
            'follow_redirects' => true,
            'verify_ssl' => true,
            'failure_threshold' => $this->settings->default_failure_threshold,
            'recovery_threshold' => $this->settings->default_recovery_threshold,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'cloudways_server_id' => $serverId,
            'cloudways_app_id' => $appId,
        ]);

        $monitor->scheduleNextCheck(random_int(30, min(10 * 60, 300)));

        try {
            $monitor->save();
        } catch (UniqueConstraintViolationException) {
            return 'skipped';
        }

        return 'created';
    }
}
