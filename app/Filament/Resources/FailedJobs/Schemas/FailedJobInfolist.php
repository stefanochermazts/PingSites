<?php

namespace App\Filament\Resources\FailedJobs\Schemas;

use App\Models\Monitor;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FailedJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('failed_at')
                            ->label('Fallito il')
                            ->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('queue')
                            ->label('Coda')
                            ->badge(),
                        TextEntry::make('connection')
                            ->label('Connessione'),
                        TextEntry::make('job_name')
                            ->label('Job'),
                        TextEntry::make('monitor_id')
                            ->label('Monitor')
                            ->formatStateUsing(function (?int $state): string {
                                if (! $state) {
                                    return '-';
                                }

                                return Monitor::query()->find($state)?->name ?? '#'.$state;
                            }),
                        TextEntry::make('uuid')
                            ->label('UUID')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Eccezione')
                    ->schema([
                        TextEntry::make('exception')
                            ->label('Stack trace')
                            ->columnSpanFull()
                            ->prose()
                            ->markdown(false)
                            ->formatStateUsing(fn (?string $state): string => $state ? '<pre class="text-xs whitespace-pre-wrap overflow-x-auto">'.e($state).'</pre>' : '-')
                            ->html(),
                    ]),
            ]);
    }
}
