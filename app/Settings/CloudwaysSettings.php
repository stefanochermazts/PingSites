<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CloudwaysSettings extends Settings
{
    public ?string $access_token = null;

    public static function group(): string
    {
        return 'cloudways';
    }
}
