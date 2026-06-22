<?php

namespace App\Filament\Resources\Monitors\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'checks';

    protected static ?string $title = 'Controlli';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('checked_at', 'desc')
            ->columns([
                TextColumn::make('checked_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                IconColumn::make('success')
                    ->label('Esito')
                    ->boolean(),
                IconColumn::make('is_manual')
                    ->label('Manuale')
                    ->boolean(),
                TextColumn::make('http_code')
                    ->label('HTTP')
                    ->placeholder('-'),
                TextColumn::make('response_time_ms')
                    ->label('Tempo')
                    ->suffix(' ms')
                    ->placeholder('-'),
                TextColumn::make('error_type')
                    ->label('Errore')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->placeholder('-'),
                TextColumn::make('error_message')
                    ->label('Dettaglio')
                    ->limit(50)
                    ->placeholder('-'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
