<?php

namespace App\Console\Commands;

use App\Actions\CheckCloudwaysInfectionsAction;
use Illuminate\Console\Command;

class CheckMonitorInfectionsCommand extends Command
{
    protected $signature = 'monitors:check-infections';

    protected $description = 'Controlla su Cloudways Security Suite se i siti Publimedia risultano infetti';

    public function handle(CheckCloudwaysInfectionsAction $check): int
    {
        $result = $check->handle();

        $this->info("Aggiornati {$result['updated']} controllo/i infezione.");

        if ($result['skipped'] > 0) {
            $this->warn("Saltati {$result['skipped']} monitor senza ID Cloudways.");
        }

        if ($result['failed'] > 0) {
            $this->warn("Security Suite non disponibile per {$result['failed']} monitor.");
        }

        return self::SUCCESS;
    }
}
