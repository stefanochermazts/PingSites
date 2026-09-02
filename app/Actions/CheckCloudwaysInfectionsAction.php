<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\Cloudways\CloudwaysClient;
use App\Services\Cloudways\CloudwaysException;
use App\Services\Cloudways\CloudwaysMalwareStatusCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CheckCloudwaysInfectionsAction
{
    public function __construct(
        private readonly CloudwaysClient $client,
    ) {}

    /**
     * @return array{updated: int, skipped: int, failed: int}
     */
    public function handle(): array
    {
        $result = [
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $statusPage = StatusPage::query()
            ->where('slug', StatusPage::INFECTION_SLUG)
            ->first();

        if ($statusPage === null) {
            return $result;
        }

        $monitors = Monitor::query()
            ->where('status_page_id', $statusPage->id)
            ->whereNotNull('cloudways_server_id')
            ->whereNotNull('cloudways_app_id')
            ->get()
            ->filter(fn (Monitor $monitor): bool => filled($monitor->cloudways_server_id) && filled($monitor->cloudways_app_id));

        $catalogs = $this->catalogsByServer($monitors);

        foreach ($monitors as $monitor) {
            $serverId = (string) $monitor->cloudways_server_id;
            $catalog = $catalogs[$serverId] ?? CloudwaysMalwareStatusCatalog::unavailable();

            if (! $catalog->available) {
                $result['failed']++;

                continue;
            }

            $monitor->is_infected = $catalog->forApp((string) $monitor->cloudways_app_id);
            $monitor->infection_checked_at = now();
            $monitor->save();
            $result['updated']++;
        }

        $result['skipped'] = Monitor::query()
            ->where('status_page_id', $statusPage->id)
            ->where(function ($query): void {
                $query->whereNull('cloudways_server_id')
                    ->orWhereNull('cloudways_app_id')
                    ->orWhere('cloudways_server_id', '')
                    ->orWhere('cloudways_app_id', '');
            })
            ->count();

        return $result;
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @return array<string, CloudwaysMalwareStatusCatalog>
     */
    private function catalogsByServer(Collection $monitors): array
    {
        $catalogs = [];

        foreach ($monitors->pluck('cloudways_server_id')->unique()->filter() as $serverId) {
            $serverId = (string) $serverId;

            try {
                $catalogs[$serverId] = CloudwaysMalwareStatusCatalog::fromSecurityApps(
                    $this->client->securityAppsForServer($serverId)
                );
            } catch (CloudwaysException $exception) {
                Log::warning('Security Suite Cloudways non disponibile', [
                    'server_id' => $serverId,
                    'error' => $exception->getMessage(),
                ]);
                $catalogs[$serverId] = CloudwaysMalwareStatusCatalog::unavailable();
            }
        }

        return $catalogs;
    }
}
