<?php

namespace App\Filament\Resources\StatusPages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class StatusPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Status page')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome interno')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('title')
                            ->label('Titolo pubblico')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, $get): void {
                                if (filled($get('slug'))) {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            }),
                        TextInput::make('slug')
                            ->label('Slug URL')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Usato nell\'URL pubblico: /status/{slug}'),
                        Toggle::make('is_default')
                            ->label('Pagina predefinita')
                            ->helperText('Usata quando si visita /status')
                            ->default(false),
                    ]),
            ]);
    }
}
