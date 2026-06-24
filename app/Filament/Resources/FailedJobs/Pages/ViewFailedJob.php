<?php

namespace App\Filament\Resources\FailedJobs\Pages;

use App\Filament\Resources\FailedJobs\FailedJobResource;
use App\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ViewFailedJob extends ViewRecord
{
    protected static string $resource = FailedJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('Riprova job')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (FailedJob $record): void {
                    Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                    Notification::make()
                        ->title('Job rimesso in coda')
                        ->success()
                        ->send();
                }),
            Action::make('delete')
                ->label('Elimina')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (FailedJob $record): void {
                    Artisan::call('queue:forget', ['id' => $record->uuid]);

                    Notification::make()
                        ->title('Failed job eliminato')
                        ->success()
                        ->send();

                    $this->redirect(FailedJobResource::getUrl('index'));
                }),
        ];
    }
}
