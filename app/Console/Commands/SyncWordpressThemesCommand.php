<?php

namespace App\Console\Commands;

use App\Actions\SyncWordpressThemesAction;
use Illuminate\Console\Command;

class SyncWordpressThemesCommand extends Command
{
    protected $signature = 'monitors:sync-themes';

    protected $description = 'Rileva il tema WordPress attivo dei siti Publimedia';

    public function handle(SyncWordpressThemesAction $sync): int
    {
        $result = $sync->handle();

        $this->info("Aggiornati {$result['updated']} tema/i WordPress.");

        if ($result['skipped'] > 0) {
            $this->warn("Saltati {$result['skipped']} monitor senza URL.");
        }

        if ($result['failed'] > 0) {
            $this->warn("HTML non disponibile per {$result['failed']} monitor.");
        }

        return self::SUCCESS;
    }
}
