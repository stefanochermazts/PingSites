<?php

namespace Tests;

use Database\Seeders\CloudwaysSettingsSeeder;
use Database\Seeders\MonitorSettingsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedMonitorSettings(): void
    {
        $this->seed(MonitorSettingsSeeder::class);
        $this->seed(CloudwaysSettingsSeeder::class);
    }
}
