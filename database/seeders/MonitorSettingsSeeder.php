<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MonitorSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'status_page_title' => 'Devisia Status',
            'alert_recipients' => env('MONITOR_ALERT_RECIPIENTS', 'admin@example.com'),
            'mail_from_address' => env('MAIL_FROM_ADDRESS', 'monitor@example.com'),
            'mail_from_name' => env('MAIL_FROM_NAME', 'Devisia Monitor'),
            'default_check_frequency' => 5,
            'default_timeout' => 10,
            'default_valid_status_codes' => [200, 301, 302],
            'default_failure_threshold' => 2,
            'default_recovery_threshold' => 2,
            'check_retention_days' => 30,
            'notification_log_retention_days' => 365,
            'user_agent' => 'DevisiaMonitor/1.0 (+https://devisia.pro)',
        ];

        foreach ($defaults as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'monitor', 'name' => $name],
                ['payload' => json_encode($value), 'locked' => false, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
