<?php

namespace App\Console\Commands;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Console\Command;

class DispatchMonitorChecksCommand extends Command
{
    protected $signature = 'monitors:dispatch-checks';

    protected $description = 'Dispatch due monitor checks to the queue';

    public function handle(): int
    {
        $monitors = Monitor::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->orderBy('next_check_at')
            ->get();

        foreach ($monitors as $monitor) {
            CheckMonitorJob::dispatch($monitor->id);

            $monitor->scheduleNextCheck();
            $monitor->save();
        }

        $this->info("Dispatched {$monitors->count()} monitor check(s).");

        return self::SUCCESS;
    }
}
