<?php

namespace App\Filament\Resources\FailedJobs\Pages;

use App\Filament\Resources\FailedJobs\FailedJobResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ListFailedJobs extends ListRecords
{
    protected static string $resource = FailedJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('truncate')
                ->label('Svuota tabella')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                ->modalHeading('Svuota tabella failed_jobs')
                ->modalDescription('Elimina definitivamente tutti i record dalla tabella failed_jobs. Usa questa operazione solo per ripulire accumuli massivi di job falliti, come dopo un bug di sistema. I job non verranno riprocessati.')
                ->modalSubmitActionLabel('Svuota tabella')
                ->action(function (): void {
                    DB::table('failed_jobs')->truncate();

                    Notification::make()
                        ->title('Tabella failed_jobs svuotata')
                        ->success()
                        ->send();
                }),
        ];
    }
}
