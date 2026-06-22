<?php

namespace Tests;

use Database\Seeders\MonitorSettingsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedMonitorSettings(): void
    {
        $this->seed(MonitorSettingsSeeder::class);
    }
}
