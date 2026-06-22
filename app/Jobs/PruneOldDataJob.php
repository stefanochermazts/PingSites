<?php

namespace App\Jobs;

use App\Models\Check;
use App\Models\NotificationLog;
use App\Settings\MonitorSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class PruneOldDataJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('cleanup');
    }

    public function handle(MonitorSettings $settings): void
    {
        Check::query()
            ->where('checked_at', '<', now()->subDays($settings->check_retention_days))
            ->delete();

        NotificationLog::query()
            ->where('created_at', '<', now()->subDays($settings->notification_log_retention_days))
            ->delete();
    }
}
