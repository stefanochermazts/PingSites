<?php

namespace App\Filament\Resources\Incidents\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IncidentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Incidente')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('monitor.name')->label('Monitor'),
                        TextEntry::make('status')
                            ->label('Stato')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->label())
                            ->color(fn ($state) => $state?->color()),
                        TextEntry::make('opened_at')->label('Apertura')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('closed_at')->label('Chiusura')->dateTime('d/m/Y H:i:s')->placeholder('-'),
                        TextEntry::make('initial_cause')->label('Causa iniziale'),
                        TextEntry::make('last_error_type')
                            ->label('Ultimo errore')
                            ->formatStateUsing(fn ($state) => $state?->label())
                            ->placeholder('-'),
                        TextEntry::make('failed_checks_count')->label('Check falliti'),
                        TextEntry::make('duration_seconds')
                            ->label('Durata')
                            ->formatStateUsing(fn (?int $state) => $state ? gmdate('H:i:s', $state) : '-'),
                        TextEntry::make('public_message')->label('Messaggio pubblico')->columnSpanFull()->placeholder('-'),
                    ]),
                Section::make('Timeline')
                    ->schema([
                        RepeatableEntry::make('events')
                            ->label('')
                            ->schema([
                                TextEntry::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('type')->label('Evento')->formatStateUsing(fn ($state) => $state?->label()),
                                TextEntry::make('message')->label('Dettaglio')->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
