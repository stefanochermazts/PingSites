<?php

namespace App\Filament\Resources\Monitors\Schemas;

use App\Models\Monitor;
use App\Services\MonitorReportService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MonitorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $reportService = app(MonitorReportService::class);

        return $schema
            ->components([
                Section::make('Stato attuale')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Stato')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->label())
                            ->color(fn ($state) => $state?->color()),
                        TextEntry::make('last_checked_at')
                            ->label('Ultimo controllo')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('next_check_at')
                            ->label('Prossimo controllo')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('last_http_code')
                            ->label('Ultimo codice HTTP')
                            ->placeholder('-'),
                        TextEntry::make('last_response_time_ms')
                            ->label('Ultimo tempo risposta')
                            ->suffix(' ms')
                            ->placeholder('-'),
                        TextEntry::make('last_error_type')
                            ->label('Ultimo errore')
                            ->formatStateUsing(fn ($state) => $state?->label())
                            ->placeholder('-'),
                    ]),
                Section::make('Report')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('report.uptime_7')
                            ->label('Uptime 7 giorni')
                            ->state(function (Monitor $record) use ($reportService) {
                                $value = $reportService->uptimePercentage($record, 7);

                                return $value !== null ? "{$value}%" : 'N/D';
                            }),
                        TextEntry::make('report.uptime_30')
                            ->label('Uptime 30 giorni')
                            ->state(function (Monitor $record) use ($reportService) {
                                $value = $reportService->uptimePercentage($record, 30);

                                return $value !== null ? "{$value}%" : 'N/D';
                            }),
                        TextEntry::make('report.incidents')
                            ->label('Incidenti (30 gg)')
                            ->state(fn (Monitor $record) => $reportService->incidentsCount($record, 30)),
                        TextEntry::make('report.downtime')
                            ->label('Downtime (30 gg)')
                            ->state(function (Monitor $record) use ($reportService) {
                                return $reportService->formatDuration($reportService->downtimeSeconds($record, 30));
                            }),
                        TextEntry::make('report.avg_response')
                            ->label('Tempo medio risposta')
                            ->state(function (Monitor $record) use ($reportService) {
                                $value = $reportService->averageResponseTime($record, 30);

                                return $value !== null ? "{$value} ms" : 'N/D';
                            }),
                    ]),
                Section::make('Dettagli')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nome interno'),
                        TextEntry::make('url')->label('URL')->copyable(),
                        TextEntry::make('public_name')->label('Nome pubblico')->placeholder('-'),
                        TextEntry::make('check_frequency')->label('Frequenza')->suffix(' min'),
                        TextEntry::make('timeout')->label('Timeout')->suffix(' s'),
                        TextEntry::make('keyword')->label('Keyword')->placeholder('-'),
                        TextEntry::make('internal_notes')->label('Note interne')->columnSpanFull(),
                    ]),
            ]);
    }
}
