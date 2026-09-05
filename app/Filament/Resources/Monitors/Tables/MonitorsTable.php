<?php

namespace App\Filament\Resources\Monitors\Tables;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Services\HttpChecker;
use App\Services\IncidentManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonitorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->searchPlaceholder('Cerca per nome, URL o stato')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('public_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                TextColumn::make('url')
                    ->label('URL')
                    ->limit(40)
                    ->tooltip(fn (Monitor $record) => $record->url)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (?MonitorStatus $state) => $state?->label())
                    ->color(fn (?MonitorStatus $state) => $state?->color())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $values = collect(MonitorStatus::cases())
                            ->filter(fn (MonitorStatus $status): bool => str_contains(
                                mb_strtolower($status->label().' '.$status->value),
                                mb_strtolower(trim($search)),
                            ))
                            ->map(fn (MonitorStatus $status): string => $status->value)
                            ->all();

                        return $query->whereIn('status', $values !== [] ? $values : ['__none__']);
                    }),
                TextColumn::make('last_checked_at')
                    ->label('Ultimo controllo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('last_http_code')
                    ->label('HTTP')
                    ->placeholder('-'),
                TextColumn::make('last_response_time_ms')
                    ->label('Tempo risposta')
                    ->suffix(' ms')
                    ->placeholder('-'),
                TextColumn::make('next_check_at')
                    ->label('Prossimo controllo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('statusPage.name')
                    ->label('Status page')
                    ->placeholder('-')
                    ->toggleable(),
                IconColumn::make('published')
                    ->label('Pubblicato')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(collect(MonitorStatus::cases())->mapWithKeys(fn (MonitorStatus $status) => [$status->value => $status->label()])),
            ])
            ->recordActions([
                Action::make('runCheck')
                    ->label('Check manuale')
                    ->icon(Heroicon::OutlinedPlay)
                    ->action(function (Monitor $record, HttpChecker $httpChecker, IncidentManager $incidentManager): void {
                        $result = $httpChecker->check($record);
                        $incidentManager->processManualCheck($record, $result);

                        Notification::make()
                            ->title($result->success ? 'Check riuscito' : 'Check fallito')
                            ->body($result->errorMessage ?? 'Controllo completato.')
                            ->success($result->success)
                            ->danger(! $result->success)
                            ->send();
                    }),
                Action::make('pause')
                    ->label('Sospendi')
                    ->icon(Heroicon::OutlinedPause)
                    ->visible(fn (Monitor $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->action(function (Monitor $record): void {
                        $record->update([
                            'is_active' => false,
                            'status' => MonitorStatus::Paused,
                        ]);
                    }),
                Action::make('resume')
                    ->label('Riattiva')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->visible(fn (Monitor $record) => ! $record->is_active)
                    ->action(function (Monitor $record): void {
                        $record->update([
                            'is_active' => true,
                            'status' => MonitorStatus::Unknown,
                        ]);
                        $record->scheduleNextCheck(random_int(30, 120));
                        $record->save();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
