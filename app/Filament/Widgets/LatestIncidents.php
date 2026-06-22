<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Incidents\IncidentResource;
use App\Models\Incident;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestIncidents extends TableWidget
{
    protected static ?string $heading = 'Ultimi incidenti';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Incident::query()->with('monitor')->latest('opened_at')->limit(10)
            )
            ->columns([
                TextColumn::make('monitor.name')->label('Monitor'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->color()),
                TextColumn::make('opened_at')->label('Apertura')->dateTime('d/m/Y H:i'),
                TextColumn::make('initial_cause')->label('Causa')->limit(40),
            ])
            ->recordUrl(fn (Incident $record) => IncidentResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
