<?php

namespace App\Console\Commands;

use App\Actions\SyncCloudwaysMonitorUrlsAction;
use App\Services\Cloudways\CloudwaysClient;
use App\Services\Cloudways\CloudwaysException;
use Illuminate\Console\Command;

class SyncCloudwaysMonitorUrlsCommand extends Command
{
    protected $signature = 'cloudways:sync-monitor-urls';

    protected $description = 'Aggiorna gli URL dei monitor importati da Cloudways se sono cambiati';

    public function handle(CloudwaysClient $client, SyncCloudwaysMonitorUrlsAction $sync): int
    {
        try {
            $client->resolveToken();
        } catch (CloudwaysException $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        try {
            $result = $sync->handle();
        } catch (CloudwaysException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Sync Cloudways completato: aggiornati: %d, invariati: %d, mancanti: %d, errori: %d',
            $result['updated'],
            $result['unchanged'],
            $result['missing'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
