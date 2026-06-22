<?php

namespace App\Filament\Resources\NotificationLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotificationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('monitor.name')->label('Monitor')->searchable(),
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('to_email')->label('Destinatario'),
                TextColumn::make('subject')->label('Oggetto')->limit(40),
                TextColumn::make('status')->label('Stato')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('sent_at')->label('Inviata')->dateTime('d/m/Y H:i')->placeholder('-'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
