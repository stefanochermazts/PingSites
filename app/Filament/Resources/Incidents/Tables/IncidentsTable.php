<?php

namespace App\Filament\Resources\Incidents\Tables;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Services\IncidentCleanupService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IncidentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('monitor.name')
                    ->label('Monitor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->color()),
                TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Chiusura')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextColumn::make('initial_cause')
                    ->label('Causa iniziale')
                    ->limit(40),
                TextColumn::make('failed_checks_count')
                    ->label('Check falliti'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'open' => 'Aperto',
                        'resolved' => 'Risolto',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('close')
                    ->label('Chiudi')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('warning')
                    ->visible(fn (Incident $record): bool => $record->isOpen())
                    ->requiresConfirmation()
                    ->action(function (Incident $record, IncidentCleanupService $cleanup): void {
                        $cleanup->closeOpenIncidents(collect([$record]));

                        Notification::make()
                            ->title('Incidente chiuso')
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->label('Elimina')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Incident $record): bool => $record->status === IncidentStatus::Resolved)
                    ->requiresConfirmation()
                    ->action(function (Incident $record, IncidentCleanupService $cleanup): void {
                        $cleanup->deleteResolvedIncidents(collect([$record]));

                        Notification::make()
                            ->title('Incidente eliminato')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('closeSelected')
                        ->label('Chiudi selezionati')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records, IncidentCleanupService $cleanup): void {
                            $closed = $cleanup->closeOpenIncidents($records);

                            Notification::make()
                                ->title("Chiusi {$closed} incidenti")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('deleteSelected')
                        ->label('Elimina selezionati')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Verranno eliminati solo gli incidenti già risolti tra quelli selezionati.')
                        ->action(function ($records, IncidentCleanupService $cleanup): void {
                            $resolved = $records->filter(
                                fn (Incident $record): bool => $record->status === IncidentStatus::Resolved,
                            );

                            $deleted = $cleanup->deleteResolvedIncidents($resolved);

                            Notification::make()
                                ->title($deleted > 0 ? "Eliminati {$deleted} incidenti" : 'Nessun incidente risolto da eliminare')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
