<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Filament\Resources\Incidents\IncidentResource;
use App\Services\IncidentCleanupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListIncidents extends ListRecords
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeAllOpen')
                ->label('Chiudi tutti gli aperti')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Chiudi tutti gli incidenti aperti')
                ->modalDescription('Segna come risolti tutti gli incidenti ancora aperti e ripristina lo stato dei monitor collegati. Usa questa azione dopo bug di sistema o falsi positivi.')
                ->modalSubmitActionLabel('Chiudi tutti')
                ->action(function (IncidentCleanupService $cleanup): void {
                    $closed = $cleanup->closeOpenIncidents();

                    Notification::make()
                        ->title($closed > 0 ? "Chiusi {$closed} incidenti" : 'Nessun incidente aperto')
                        ->success()
                        ->send();
                }),
            Action::make('deleteResolved')
                ->label('Elimina incidenti risolti')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Elimina incidenti risolti')
                ->modalDescription('Elimina definitivamente tutti gli incidenti già risolti e la relativa timeline. I monitor e lo storico check non vengono toccati.')
                ->modalSubmitActionLabel('Elimina risolti')
                ->action(function (IncidentCleanupService $cleanup): void {
                    $deleted = $cleanup->deleteResolvedIncidents();

                    Notification::make()
                        ->title($deleted > 0 ? "Eliminati {$deleted} incidenti" : 'Nessun incidente risolto')
                        ->success()
                        ->send();
                }),
        ];
    }
}
