<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Filament\Resources\Monitors\MonitorResource;
use App\Services\HttpChecker;
use App\Services\IncidentManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMonitor extends ViewRecord
{
    protected static string $resource = MonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runCheck')
                ->label('Check manuale')
                ->icon(Heroicon::OutlinedPlay)
                ->action(function (HttpChecker $httpChecker, IncidentManager $incidentManager): void {
                    $result = $httpChecker->check($this->record);
                    $incidentManager->processManualCheck($this->record, $result);

                    Notification::make()
                        ->title($result->success ? 'Check riuscito' : 'Check fallito')
                        ->body($result->errorMessage ?? 'Controllo completato.')
                        ->success($result->success)
                        ->danger(! $result->success)
                        ->send();

                    $this->refreshFormData(['status', 'last_checked_at', 'last_http_code', 'last_response_time_ms', 'last_error_type']);
                }),
            EditAction::make(),
        ];
    }
}
