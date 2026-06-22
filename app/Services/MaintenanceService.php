<?php

namespace App\Services;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class MaintenanceService
{
    public function isMonitorInMaintenance(Monitor $monitor): bool
    {
        return MaintenanceWindow::query()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereHas('monitors', fn ($query) => $query->where('monitors.id', $monitor->id))
            ->exists();
    }

    /**
     * @return Collection<int, MaintenanceWindow>
     */
    public function activeForMonitor(Monitor $monitor)
    {
        return MaintenanceWindow::query()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereHas('monitors', fn ($query) => $query->where('monitors.id', $monitor->id))
            ->get();
    }

    /**
     * @return Collection<int, MaintenanceWindow>
     */
    /**
     * @param  Collection<int, int>  $monitorIds
     * @return Collection<int, MaintenanceWindow>
     */
    public function publicActiveOrUpcomingForMonitors(BaseCollection $monitorIds)
    {
        if ($monitorIds->isEmpty()) {
            return new Collection;
        }

        return MaintenanceWindow::query()
            ->where('public_visible', true)
            ->where('ends_at', '>=', now())
            ->whereHas('monitors', fn ($query) => $query->whereIn('monitors.id', $monitorIds))
            ->with('monitors')
            ->orderBy('starts_at')
            ->get();
    }
}
