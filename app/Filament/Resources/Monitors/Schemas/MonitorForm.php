<?php

namespace App\Filament\Resources\Monitors\Schemas;

use App\Rules\PublicMonitorUrl;
use App\Settings\MonitorSettings;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MonitorForm
{
    public static function configure(Schema $schema): Schema
    {
        $defaults = app(MonitorSettings::class);

        return $schema
            ->components([
                Section::make('Generale')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome interno')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->rules([new PublicMonitorUrl]),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                        Toggle::make('published')
                            ->label('Pubblicato in status page')
                            ->default(false),
                        TextInput::make('public_name')
                            ->label('Nome pubblico')
                            ->maxLength(255),
                        Textarea::make('internal_notes')
                            ->label('Note interne')
                            ->columnSpanFull(),
                    ]),
                Section::make('Controllo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('check_frequency')
                            ->label('Frequenza (minuti)')
                            ->numeric()
                            ->default($defaults->default_check_frequency)
                            ->minValue(1)
                            ->required(),
                        TextInput::make('timeout')
                            ->label('Timeout (secondi)')
                            ->numeric()
                            ->default($defaults->default_timeout)
                            ->minValue(1)
                            ->required(),
                        TagsInput::make('valid_status_codes')
                            ->label('Codici HTTP validi')
                            ->default($defaults->default_valid_status_codes)
                            ->placeholder('200')
                            ->required(),
                        TextInput::make('keyword')
                            ->label('Keyword opzionale')
                            ->maxLength(255),
                        Toggle::make('follow_redirects')
                            ->label('Segui redirect')
                            ->default(true),
                        Toggle::make('verify_ssl')
                            ->label('Verifica SSL')
                            ->default(true),
                        TextInput::make('failure_threshold')
                            ->label('Fallimenti per aprire incidente')
                            ->numeric()
                            ->default($defaults->default_failure_threshold)
                            ->minValue(1)
                            ->required(),
                        TextInput::make('recovery_threshold')
                            ->label('Successi per chiudere incidente')
                            ->numeric()
                            ->default($defaults->default_recovery_threshold)
                            ->minValue(1)
                            ->required(),
                    ]),
            ]);
    }
}
