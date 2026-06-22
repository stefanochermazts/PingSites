<?php

namespace App\Filament\Resources\StatusPages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome interno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Titolo pubblico')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('URL')
                    ->formatStateUsing(fn (string $state): string => '/status/'.$state)
                    ->copyable()
                    ->copyMessage('URL copiato'),
                TextColumn::make('published_monitors_count')
                    ->label('Monitor pubblicati')
                    ->counts(['monitors as published_monitors_count' => fn ($query) => $query->where('published', true)])
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Predefinita')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
