<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MonitorSettings extends Settings
{
    public string $status_page_title;

    public string $alert_recipients;

    public string $mail_from_address;

    public string $mail_from_name;

    public int $default_check_frequency;

    public int $default_timeout;

    /** @var array<int> */
    public array $default_valid_status_codes;

    public int $default_failure_threshold;

    public int $default_recovery_threshold;

    public int $check_retention_days;

    public int $notification_log_retention_days;

    public string $user_agent;

    public static function group(): string
    {
        return 'monitor';
    }
}
