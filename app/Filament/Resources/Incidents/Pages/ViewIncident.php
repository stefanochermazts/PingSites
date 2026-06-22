<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Filament\Resources\Incidents\IncidentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewIncident extends ViewRecord
{
    protected static string $resource = IncidentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return parent::getRecord()->load('events', 'monitor');
    }
}
