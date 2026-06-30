<?php

namespace App\Services;

use App\Enums\IncidentEventType;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\IncidentEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncidentCleanupService
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService,
    ) {}

    public function closeOpenIncidents(?Collection $incidents = null): int
    {
        $query = Incident::query()
            ->where('status', IncidentStatus::Open)
            ->with('monitor');

        if ($incidents !== null) {
            $query->whereIn('id', $incidents->pluck('id'));
        }

        $closed = 0;

        DB::transaction(function () use ($query, &$closed): void {
            $query->lockForUpdate()->get()->each(function (Incident $incident) use (&$closed): void {
                if (! $incident->isOpen()) {
                    return;
                }

                $this->closeIncident($incident);
                $closed++;
            });
        });

        return $closed;
    }

    public function deleteResolvedIncidents(?Collection $incidents = null): int
    {
        $query = Incident::query()->where('status', IncidentStatus::Resolved);

        if ($incidents !== null) {
            $query->whereIn('id', $incidents->pluck('id'));
        }

        return $query->delete();
    }

    private function closeIncident(Incident $incident): void
    {
        $closedAt = now();

        $incident->update([
            'closed_at' => $closedAt,
            'status' => IncidentStatus::Resolved,
            'duration_seconds' => (int) $incident->opened_at->diffInSeconds($closedAt),
            'last_positive_at' => $closedAt,
        ]);

        IncidentEvent::query()->create([
            'incident_id' => $incident->id,
            'type' => IncidentEventType::Closed,
            'message' => 'Incidente chiuso manualmente dall\'amministratore.',
            'created_at' => $closedAt,
        ]);

        $monitor = $incident->monitor;

        if ($monitor === null || $monitor->isPaused()) {
            return;
        }

        $monitor->update([
            'status' => $this->maintenanceService->isMonitorInMaintenance($monitor)
                ? MonitorStatus::Maintenance
                : MonitorStatus::Online,
            'consecutive_failures' => 0,
            'consecutive_successes' => 0,
        ]);
    }
}
