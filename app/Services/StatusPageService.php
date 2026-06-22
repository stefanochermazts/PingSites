<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Settings\MonitorSettings;
use Illuminate\Support\Collection;

class StatusPageService
{
    public function __construct(
        private readonly MonitorSettings $settings,
        private readonly MaintenanceService $maintenanceService,
    ) {}

    public function data(): array
    {
        $monitors = Monitor::query()
            ->where('published', true)
            ->orderBy('public_name')
            ->orderBy('name')
            ->get();

        $openIncidents = Incident::query()
            ->where('status', IncidentStatus::Open)
            ->where('public_visible', true)
            ->whereHas('monitor', fn ($query) => $query->where('published', true))
            ->with('monitor')
            ->orderByDesc('opened_at')
            ->get();

        $maintenances = $this->maintenanceService->publicActiveOrUpcoming();

        $recentIncidents = Incident::query()
            ->where('public_visible', true)
            ->whereHas('monitor', fn ($query) => $query->where('published', true))
            ->where('opened_at', '>=', now()->subDays(30))
            ->with('monitor')
            ->orderByDesc('opened_at')
            ->limit(10)
            ->get();

        return [
            'title' => $this->settings->status_page_title,
            'overall_status' => $this->overallStatus($monitors, $maintenances),
            'overall_status_label' => $this->overallStatusLabel($monitors, $maintenances),
            'monitors' => $monitors->map(fn (Monitor $monitor) => [
                'name' => $monitor->displayPublicName(),
                'status' => $this->publicMonitorStatus($monitor),
                'status_label' => $this->publicMonitorStatusLabel($monitor),
            ])->values()->all(),
            'open_incidents' => $openIncidents->map(fn (Incident $incident) => [
                'name' => $incident->monitor->displayPublicName(),
                'message' => $incident->publicMessage(),
                'opened_at' => $incident->opened_at->toIso8601String(),
            ])->values()->all(),
            'maintenances' => $maintenances->map(fn ($maintenance) => [
                'title' => $maintenance->title,
                'message' => $maintenance->public_message ?: 'Manutenzione programmata.',
                'starts_at' => $maintenance->starts_at->toIso8601String(),
                'ends_at' => $maintenance->ends_at->toIso8601String(),
                'is_active' => $maintenance->isActive(),
            ])->values()->all(),
            'recent_incidents' => $recentIncidents->map(fn (Incident $incident) => [
                'name' => $incident->monitor->displayPublicName(),
                'status' => $incident->status->label(),
                'opened_at' => $incident->opened_at->toIso8601String(),
                'closed_at' => $incident->closed_at?->toIso8601String(),
            ])->values()->all(),
            'updated_at' => Monitor::query()->where('published', true)->max('last_checked_at'),
        ];
    }

    public static function cacheKey(): string
    {
        return 'status-page';
    }

    public static function forgetCache(): void
    {
        cache()->forget(self::cacheKey());
    }

    private function overallStatus(Collection $monitors, Collection $maintenances): string
    {
        if ($monitors->isEmpty()) {
            return 'unavailable';
        }

        if ($monitors->contains(fn (Monitor $m) => $this->publicMonitorStatus($m) === 'down')) {
            return 'degraded';
        }

        if ($maintenances->contains(fn ($m) => $m->isActive()) ||
            $monitors->contains(fn (Monitor $m) => $this->publicMonitorStatus($m) === 'maintenance')) {
            return 'maintenance';
        }

        return 'operational';
    }

    private function overallStatusLabel(Collection $monitors, Collection $maintenances): string
    {
        return match ($this->overallStatus($monitors, $maintenances)) {
            'degraded' => 'Problemi su uno o più servizi',
            'maintenance' => 'Manutenzione in corso',
            'operational' => 'Tutti i servizi operativi',
            default => 'Stato non disponibile',
        };
    }

    private function publicMonitorStatus(Monitor $monitor): string
    {
        if ($this->maintenanceService->isMonitorInMaintenance($monitor)) {
            return 'maintenance';
        }

        return match ($monitor->status) {
            MonitorStatus::Down => 'down',
            MonitorStatus::Maintenance => 'maintenance',
            MonitorStatus::Online => 'operational',
            default => 'unknown',
        };
    }

    private function publicMonitorStatusLabel(Monitor $monitor): string
    {
        return match ($this->publicMonitorStatus($monitor)) {
            'down' => 'Problemi rilevati',
            'maintenance' => 'Manutenzione',
            'operational' => 'Operativo',
            default => 'Stato non disponibile',
        };
    }
}
