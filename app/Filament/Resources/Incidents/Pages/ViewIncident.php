<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Enums\IncidentStatus;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Services\IncidentCleanupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewIncident extends ViewRecord
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('close')
                ->label('Chiudi incidente')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord()->isOpen())
                ->requiresConfirmation()
                ->action(function (IncidentCleanupService $cleanup): void {
                    $cleanup->closeOpenIncidents(collect([$this->getRecord()]));

                    Notification::make()
                        ->title('Incidente chiuso')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'closed_at', 'duration_seconds']);
                }),
            Action::make('delete')
                ->label('Elimina')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->status === IncidentStatus::Resolved)
                ->requiresConfirmation()
                ->action(function (IncidentCleanupService $cleanup): void {
                    $cleanup->deleteResolvedIncidents(collect([$this->getRecord()]));

                    Notification::make()
                        ->title('Incidente eliminato')
                        ->success()
                        ->send();

                    $this->redirect(IncidentResource::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    public function getRecord(): Model
    {
        return parent::getRecord()->load('events', 'monitor');
    }
}
