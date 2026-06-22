<?php

namespace App\Console\Commands;

use App\Jobs\PruneOldDataJob;
use Illuminate\Console\Command;

class PruneChecksCommand extends Command
{
    protected $signature = 'checks:prune';

    protected $description = 'Prune old checks and notification logs';

    public function handle(): int
    {
        PruneOldDataJob::dispatch();

        $this->info('Cleanup job dispatched to the cleanup queue.');

        return self::SUCCESS;
    }
}
