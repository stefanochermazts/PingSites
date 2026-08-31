<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Actions\ImportCloudwaysMonitorsAction;
use App\Filament\Resources\Monitors\MonitorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMonitors extends ListRecords
{
    protected static string $resource = MonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportCloudwaysMonitorsAction::make(),
            CreateAction::make(),
        ];
    }
}
