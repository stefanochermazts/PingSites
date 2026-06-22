<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\HttpChecker;
use App\Services\IncidentManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckMonitorJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('checks');
    }

    public function handle(HttpChecker $httpChecker, IncidentManager $incidentManager): void
    {
        $monitor = Monitor::query()->find($this->monitorId);

        if (! $monitor || $monitor->isPaused()) {
            return;
        }

        $result = $httpChecker->check($monitor);
        $incidentManager->processAutomaticCheck($monitor, $result);

        $monitor->refresh();
        $monitor->scheduleNextCheck();
        $monitor->save();
    }
}
