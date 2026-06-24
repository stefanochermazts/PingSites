<?php

namespace App\Filament\Resources\FailedJobs\Tables;

use App\Models\FailedJob;
use App\Models\Monitor;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class FailedJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('failed_at')
                    ->label('Fallito il')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('queue')
                    ->label('Coda')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('job_name')
                    ->label('Job')
                    ->searchable(query: function ($query, string $search) {
                        $query->where('payload', 'like', '%'.$search.'%');
                    }),
                TextColumn::make('monitor_id')
                    ->label('Monitor')
                    ->formatStateUsing(function (?int $state): string {
                        if (! $state) {
                            return '-';
                        }

                        return Monitor::query()->find($state)?->name ?? '#'.$state;
                    }),
                TextColumn::make('connection')
                    ->label('Connessione')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->copyable()
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exception_summary')
                    ->label('Errore')
                    ->wrap()
                    ->limit(120),
            ])
            ->filters([
                SelectFilter::make('queue')
                    ->label('Coda')
                    ->options(fn (): array => FailedJob::query()
                        ->distinct()
                        ->orderBy('queue')
                        ->pluck('queue', 'queue')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Riprova')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        Notification::make()
                            ->title('Job rimesso in coda')
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->label('Elimina')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:forget', ['id' => $record->uuid]);

                        Notification::make()
                            ->title('Failed job eliminato')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retrySelected')
                        ->label('Riprova selezionati')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action(function ($records): void {
                            Artisan::call('queue:retry', [
                                'id' => $records->pluck('uuid')->all(),
                            ]);

                            Notification::make()
                                ->title('Job selezionati rimessi in coda')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('deleteSelected')
                        ->label('Elimina selezionati')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                Artisan::call('queue:forget', ['id' => $record->uuid]);
                            }

                            Notification::make()
                                ->title('Failed job selezionati eliminati')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
