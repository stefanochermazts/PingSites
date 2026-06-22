<?php

namespace App\Services;

use App\Models\Check;
use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Carbon;

class MonitorReportService
{
    public function forMonitor(Monitor $monitor): array
    {
        return [
            'uptime_7_days' => $this->uptimePercentage($monitor, 7),
            'uptime_30_days' => $this->uptimePercentage($monitor, 30),
            'incidents_30_days' => $this->incidentsCount($monitor, 30),
            'downtime_30_days_seconds' => $this->downtimeSeconds($monitor, 30),
            'average_response_time_ms' => $this->averageResponseTime($monitor, 30),
        ];
    }

    public function uptimePercentage(Monitor $monitor, int $days): ?float
    {
        $checks = Check::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', now()->subDays($days))
            ->where('is_manual', false)
            ->get(['success']);

        if ($checks->isEmpty()) {
            return null;
        }

        $successful = $checks->where('success', true)->count();

        return round(($successful / $checks->count()) * 100, 2);
    }

    public function incidentsCount(Monitor $monitor, int $days): int
    {
        return Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('opened_at', '>=', now()->subDays($days))
            ->count();
    }

    public function downtimeSeconds(Monitor $monitor, int $days): int
    {
        $since = now()->subDays($days);

        $incidents = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('opened_at', '>=', $since)
            ->get();

        $total = 0;

        foreach ($incidents as $incident) {
            $end = $incident->closed_at ?? now();
            $start = $incident->opened_at->lt($since) ? $since : $incident->opened_at;
            $total += (int) $start->diffInSeconds($end);
        }

        return $total;
    }

    public function averageResponseTime(Monitor $monitor, int $days): ?int
    {
        $average = Check::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', now()->subDays($days))
            ->whereNotNull('response_time_ms')
            ->avg('response_time_ms');

        return $average !== null ? (int) round($average) : null;
    }

    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            return (int) floor($seconds / 60).'m '.($seconds % 60).'s';
        }

        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        return "{$hours}h {$minutes}m";
    }
}
