<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Enums\MonitorStatus;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Services\HttpChecker;
use App\Services\IncidentManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMonitor extends CreateRecord
{
    protected static string $resource = MonitorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = MonitorStatus::Unknown->value;
        $data['valid_status_codes'] = array_map('intval', $data['valid_status_codes'] ?? [200, 301, 302]);

        return $data;
    }

    protected function afterCreate(): void
    {
        $monitor = $this->record;

        $monitor->scheduleNextCheck(random_int(30, min($monitor->check_frequency * 60, 300)));
        $monitor->save();

        $result = app(HttpChecker::class)->check($monitor);
        app(IncidentManager::class)->processManualCheck($monitor, $result);

        Notification::make()
            ->title('Primo check eseguito')
            ->body($result->success ? 'Il monitor risponde correttamente.' : ($result->errorMessage ?? 'Check fallito.'))
            ->success($result->success)
            ->danger(! $result->success)
            ->send();
    }
}
