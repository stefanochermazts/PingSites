<?php

namespace Tests\Feature;

use App\DTOs\CheckResult;
use App\Enums\ErrorType;
use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\IncidentManager;
use App\Services\StatusPageService;
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

    public function test_successful_automatic_check_sets_unknown_monitor_online(): void
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
        $success = CheckResult::success(200, 150);

        $manager->processAutomaticCheck($monitor, $success);

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Online, $monitor->status);
        $this->assertNull($monitor->last_error_type);
    }

    public function test_successful_manual_check_sets_unknown_monitor_online(): void
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
        $success = CheckResult::success(200, 150);

        $manager->processManualCheck($monitor, $success);

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Online, $monitor->status);
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

    public function test_failures_during_open_incident_do_not_overflow_consecutive_failures_counter(): void
    {
        Queue::fake();

        $monitor = Monitor::query()->create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Unknown,
            'failure_threshold' => 2,
            'recovery_threshold' => 2,
            'valid_status_codes' => [200],
            'consecutive_failures' => 255,
        ]);

        $incident = $monitor->incidents()->create([
            'opened_at' => now()->subHour(),
            'status' => \App\Enums\IncidentStatus::Open,
            'initial_cause' => 'Timeout',
            'last_error_type' => ErrorType::Timeout,
            'failed_checks_count' => 253,
            'public_visible' => false,
        ]);

        $manager = app(IncidentManager::class);
        $failure = CheckResult::failure(ErrorType::Timeout, 'Timeout');

        $manager->processAutomaticCheck($monitor->fresh(), $failure);

        $monitor->refresh();
        $incident->refresh();

        $this->assertLessThanOrEqual(255, $monitor->consecutive_failures);
        $this->assertSame(254, $incident->failed_checks_count);
        $this->assertDatabaseCount('checks', 1);
    }

    public function test_status_page_shows_operational_when_last_check_succeeded_but_status_unknown(): void
    {
        $statusPage = StatusPage::query()->where('is_default', true)->firstOrFail();

        Monitor::query()->create([
            'name' => 'Sito A',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Unknown,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito A',
            'valid_status_codes' => [200],
            'last_checked_at' => now(),
            'last_http_code' => 200,
            'last_response_time_ms' => 120,
            'last_error_type' => null,
        ]);

        $data = app(StatusPageService::class)->data($statusPage);

        $this->assertSame('operational', $data['monitors'][0]['status']);
        $this->assertSame('Operativo', $data['monitors'][0]['status_label']);
    }
}
