<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('monitors:dispatch-checks')->everyMinute();
Schedule::command('cloudways:sync-monitor-urls')->hourly();
Schedule::command('monitors:check-infections')->hourly();
Schedule::command('checks:prune')->dailyAt('02:30');
