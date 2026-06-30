<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Services\IncidentCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_open_incidents_and_resets_monitor(): void
    {
        $monitor = Monitor::query()->create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Down,
            'consecutive_failures' => 5,
            'valid_status_codes' => [200],
        ]);

        $incident = Incident::query()->create([
            'monitor_id' => $monitor->id,
            'opened_at' => now()->subHour(),
            'status' => IncidentStatus::Open,
            'initial_cause' => 'Errore DNS',
            'failed_checks_count' => 5,
            'public_visible' => true,
        ]);

        $closed = app(IncidentCleanupService::class)->closeOpenIncidents();

        $this->assertSame(1, $closed);
        $this->assertSame(IncidentStatus::Resolved, $incident->fresh()->status);
        $this->assertNotNull($incident->fresh()->closed_at);
        $this->assertSame(MonitorStatus::Online, $monitor->fresh()->status);
        $this->assertSame(0, $monitor->fresh()->consecutive_failures);
        $this->assertDatabaseHas('incident_events', [
            'incident_id' => $incident->id,
            'type' => 'closed',
        ]);
    }

    public function test_deletes_resolved_incidents(): void
    {
        $monitor = Monitor::query()->create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'valid_status_codes' => [200],
        ]);

        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'opened_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'status' => IncidentStatus::Resolved,
            'initial_cause' => 'Errore DNS',
            'failed_checks_count' => 2,
            'public_visible' => true,
        ]);

        $deleted = app(IncidentCleanupService::class)->deleteResolvedIncidents();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseCount('incidents', 0);
    }
}
