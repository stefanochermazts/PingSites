<?php

namespace App\Services;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Collection;

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
    public function publicActiveOrUpcoming()
    {
        return MaintenanceWindow::query()
            ->where('public_visible', true)
            ->where('ends_at', '>=', now())
            ->with('monitors')
            ->orderBy('starts_at')
            ->get();
    }
}
