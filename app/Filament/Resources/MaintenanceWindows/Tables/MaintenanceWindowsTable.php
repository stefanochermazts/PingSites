<?php

namespace App\Filament\Resources\MaintenanceWindows\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceWindowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('Inizio')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('ends_at')
                    ->label('Fine')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('monitors_count')
                    ->label('Monitor')
                    ->counts('monitors'),
                IconColumn::make('public_visible')
                    ->label('Pubblica')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
