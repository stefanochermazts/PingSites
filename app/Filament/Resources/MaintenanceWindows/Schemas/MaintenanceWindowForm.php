<?php

namespace App\Filament\Resources\MaintenanceWindows\Schemas;

use App\Models\Monitor;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceWindowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Manutenzione')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('starts_at')
                            ->label('Inizio')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Fine')
                            ->required()
                            ->after('starts_at'),
                        Toggle::make('public_visible')
                            ->label('Visibile in status page')
                            ->default(true),
                        Textarea::make('public_message')
                            ->label('Messaggio pubblico')
                            ->columnSpanFull(),
                        Textarea::make('internal_notes')
                            ->label('Note interne')
                            ->columnSpanFull(),
                        Select::make('monitors')
                            ->label('Monitor coinvolti')
                            ->relationship('monitors', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
