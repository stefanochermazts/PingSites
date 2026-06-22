<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\MonitorResource;
use Filament\Resources\Pages\EditRecord;

class EditMonitor extends EditRecord
{
    protected static string $resource = MonitorResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['valid_status_codes'])) {
            $data['valid_status_codes'] = array_map('intval', $data['valid_status_codes']);
        }

        return $data;
    }
}
