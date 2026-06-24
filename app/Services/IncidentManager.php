<?php

namespace App\Services;

use App\DTOs\CheckResult;
use App\Enums\IncidentEventType;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Jobs\SendMonitorDownNotificationJob;
use App\Jobs\SendMonitorRecoveryNotificationJob;
use App\Models\Check;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

class IncidentManager
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService,
    ) {}

    public function processAutomaticCheck(Monitor $monitor, CheckResult $result): Check
    {
        return DB::transaction(function () use ($monitor, $result) {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);

            $check = $this->storeCheck($monitor, $result, isManual: false);
            $this->updateMonitorSnapshot($monitor, $result, $check->checked_at);

            if ($monitor->isPaused()) {
                return $check;
            }

            if ($this->maintenanceService->isMonitorInMaintenance($monitor)) {
                $monitor->status = MonitorStatus::Maintenance;
                $monitor->save();

                return $check;
            }

            if ($result->success) {
                $this->handleSuccess($monitor, $result, $check);
            } else {
                $this->handleFailure($monitor, $result, $check);
            }

            $monitor->save();

            return $check;
        });
    }

    public function processManualCheck(Monitor $monitor, CheckResult $result): Check
    {
        return DB::transaction(function () use ($monitor, $result) {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);

            $check = $this->storeCheck($monitor, $result, isManual: true);
            $this->updateMonitorSnapshot($monitor, $result, $check->checked_at);

            if (! $monitor->isPaused() && ! $this->maintenanceService->isMonitorInMaintenance($monitor)) {
                if ($result->success && ! $monitor->incidents()->where('status', IncidentStatus::Open)->exists()) {
                    $monitor->status = MonitorStatus::Online;
                    $monitor->consecutive_failures = 0;
                    $monitor->consecutive_successes = $this->incrementCounter($monitor->consecutive_successes);
                }
            }

            $monitor->save();

            return $check;
        });
    }

    private function storeCheck(Monitor $monitor, CheckResult $result, bool $isManual): Check
    {
        return $monitor->checks()->create([
            'success' => $result->success,
            'http_code' => $result->httpCode,
            'response_time_ms' => $result->responseTimeMs,
            'error_type' => $result->errorType,
            'error_message' => $result->errorMessage,
            'is_manual' => $isManual,
            'checked_at' => now(),
        ]);
    }

    private function updateMonitorSnapshot(Monitor $monitor, CheckResult $result, $checkedAt): void
    {
        $monitor->last_checked_at = $checkedAt;
        $monitor->last_http_code = $result->httpCode;
        $monitor->last_response_time_ms = $result->responseTimeMs;
        $monitor->last_error_type = $result->errorType;
    }

    private function handleSuccess(Monitor $monitor, CheckResult $result, Check $check): void
    {
        $monitor->consecutive_failures = 0;
        $monitor->consecutive_successes = $this->incrementCounter($monitor->consecutive_successes);

        $openIncident = $monitor->incidents()->where('status', IncidentStatus::Open)->first();

        if ($openIncident) {
            $this->recordIncidentEvent(
                $openIncident,
                IncidentEventType::CheckSucceeded,
                'Controllo riuscito. Codice HTTP: '.($result->httpCode ?? 'N/D'),
            );

            if ($monitor->consecutive_successes >= $monitor->recovery_threshold) {
                $this->closeIncident($monitor, $openIncident, $result);
            }
        } else {
            $monitor->status = MonitorStatus::Online;
        }
    }

    private function handleFailure(Monitor $monitor, CheckResult $result, Check $check): void
    {
        $monitor->consecutive_successes = 0;

        $openIncident = $monitor->incidents()->where('status', IncidentStatus::Open)->first();

        if ($openIncident) {
            $openIncident->last_error_type = $result->errorType;
            $openIncident->failed_checks_count++;
            $openIncident->save();

            $this->recordIncidentEvent(
                $openIncident,
                IncidentEventType::CheckFailed,
                ($result->errorType?->label() ?? 'Errore').': '.($result->errorMessage ?? 'Controllo fallito'),
            );

            $monitor->status = MonitorStatus::Down;
            $monitor->consecutive_failures = min($monitor->consecutive_failures, $monitor->failure_threshold);

            return;
        }

        $monitor->consecutive_failures = $this->incrementCounter($monitor->consecutive_failures);

        if ($monitor->consecutive_failures >= $monitor->failure_threshold) {
            $this->openIncident($monitor, $result);
        } else {
            $monitor->status = $monitor->status === MonitorStatus::Unknown
                ? MonitorStatus::Unknown
                : MonitorStatus::Online;
        }
    }

    private function incrementCounter(int $value): int
    {
        return min($value + 1, 255);
    }

    private function openIncident(Monitor $monitor, CheckResult $result): void
    {
        $incident = $monitor->incidents()->create([
            'opened_at' => now(),
            'status' => IncidentStatus::Open,
            'initial_cause' => $result->errorType?->label() ?? 'Errore sconosciuto',
            'last_error_type' => $result->errorType,
            'failed_checks_count' => $monitor->consecutive_failures,
            'public_visible' => $monitor->published,
        ]);

        $this->recordIncidentEvent(
            $incident,
            IncidentEventType::Opened,
            'Incidente aperto: '.($result->errorType?->label() ?? 'Errore sconosciuto'),
        );

        $monitor->status = MonitorStatus::Down;

        SendMonitorDownNotificationJob::dispatch($monitor->id, $incident->id);
    }

    private function closeIncident(Monitor $monitor, Incident $incident, CheckResult $result): void
    {
        $closedAt = now();
        $duration = (int) $incident->opened_at->diffInSeconds($closedAt);

        $incident->update([
            'closed_at' => $closedAt,
            'status' => IncidentStatus::Resolved,
            'duration_seconds' => $duration,
            'last_positive_at' => $closedAt,
        ]);

        $this->recordIncidentEvent(
            $incident,
            IncidentEventType::Closed,
            'Incidente chiuso dopo recovery confermata.',
        );

        $monitor->status = MonitorStatus::Online;
        $monitor->consecutive_failures = 0;
        $monitor->consecutive_successes = 0;

        SendMonitorRecoveryNotificationJob::dispatch($monitor->id, $incident->id);
    }

    private function recordIncidentEvent(Incident $incident, IncidentEventType $type, string $message): void
    {
        IncidentEvent::query()->create([
            'incident_id' => $incident->id,
            'type' => $type,
            'message' => $message,
            'created_at' => now(),
        ]);
    }

    public function recordNotificationEvent(Incident $incident, IncidentEventType $type, string $message): void
    {
        $this->recordIncidentEvent($incident, $type, $message);
    }
}
