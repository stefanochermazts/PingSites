<?php

namespace App\Filament\Resources\NotificationLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Notifica')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('monitor.name')->label('Monitor'),
                        TextEntry::make('type')->label('Tipo')->formatStateUsing(fn ($state) => $state?->label()),
                        TextEntry::make('to_email')->label('Destinatario'),
                        TextEntry::make('subject')->label('Oggetto')->columnSpanFull(),
                        TextEntry::make('status')->label('Stato')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                        TextEntry::make('sent_at')->label('Inviata il')->dateTime('d/m/Y H:i:s')->placeholder('-'),
                        TextEntry::make('error')->label('Errore')->columnSpanFull()->placeholder('-'),
                    ]),
            ]);
    }
}
