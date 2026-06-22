<?php

namespace Tests\Feature;

use App\DTOs\CheckResult;
use App\Enums\ErrorType;
use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Services\IncidentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IncidentManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_opens_incident_after_consecutive_failures(): void
    {
        Queue::fake();

        $monitor = Monitor::query()->create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Unknown,
            'failure_threshold' => 2,
            'recovery_threshold' => 2,
            'valid_status_codes' => [200],
        ]);

        $manager = app(IncidentManager::class);
        $failure = CheckResult::failure(ErrorType::Timeout, 'Timeout');

        $manager->processAutomaticCheck($monitor, $failure);
        $monitor->refresh();
        $this->assertEquals(1, $monitor->consecutive_failures);
        $this->assertDatabaseCount('incidents', 0);

        $manager->processAutomaticCheck($monitor->fresh(), $failure);
        $this->assertDatabaseCount('incidents', 1);
        $this->assertEquals(MonitorStatus::Down, $monitor->fresh()->status);
    }

    public function test_manual_check_does_not_open_incident(): void
    {
        $monitor = Monitor::query()->create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Unknown,
            'failure_threshold' => 2,
            'recovery_threshold' => 2,
            'valid_status_codes' => [200],
        ]);

        $manager = app(IncidentManager::class);
        $failure = CheckResult::failure(ErrorType::Timeout, 'Timeout');

        $manager->processManualCheck($monitor, $failure);
        $manager->processManualCheck($monitor->fresh(), $failure);

        $this->assertDatabaseCount('incidents', 0);
    }
}
