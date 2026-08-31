<?php

namespace App\Filament\Actions;

use App\Actions\ImportCloudwaysAppsAction;
use App\Models\StatusPage;
use App\Services\Cloudways\CloudwaysClient;
use App\Services\Cloudways\CloudwaysException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportCloudwaysMonitorsAction
{
    public static function make(): Action
    {
        return Action::make('importCloudways')
            ->label('Importa da Cloudways')
            ->icon(Heroicon::OutlinedCloudArrowDown)
            ->modalHeading('Importa applicazioni Cloudways')
            ->modalDescription('La prima volta importa tutte le app del server. Le volte successive aggiunge solo le applicazioni nuove: gli URL già importati li aggiorna lo scheduler orario.')
            ->modalSubmitActionLabel('Importa')
            ->steps([
                Step::make('Connessione')
                    ->description('Access token API Cloudways')
                    ->schema([
                        TextInput::make('access_token')
                            ->label('Access token')
                            ->password()
                            ->revealable()
                            ->required(fn (CloudwaysClient $client): bool => $client->configuredToken() === null)
                            ->helperText(function (CloudwaysClient $client): string {
                                if ($client->configuredToken() !== null) {
                                    return 'È già configurato un token. Lascia vuoto per usarlo, oppure incollane uno diverso solo per questo import.';
                                }

                                return 'Incolla l’access token Cloudways, oppure salvalo in Impostazioni / CLOUDWAYS_ACCESS_TOKEN.';
                            }),
                    ])
                    ->afterValidation(function (Get $get, CloudwaysClient $client): void {
                        $token = $get('access_token');
                        $token = is_string($token) && $token !== '' ? $token : null;

                        try {
                            $client->serverOptions($token);
                        } catch (CloudwaysException $exception) {
                            throw ValidationException::withMessages([
                                'access_token' => $exception->getMessage(),
                            ]);
                        }
                    }),
                Step::make('Destinazione')
                    ->description('Server Cloudways e status page di destinazione')
                    ->schema([
                        Select::make('server_id')
                            ->label('Server Cloudways')
                            ->options(function (Get $get, CloudwaysClient $client): array {
                                $token = $get('access_token');
                                $token = is_string($token) && $token !== '' ? $token : null;

                                try {
                                    return $client->serverOptions($token);
                                } catch (CloudwaysException) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->required(),
                        Select::make('status_page_id')
                            ->label('Status page')
                            ->options(fn () => StatusPage::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn () => StatusPage::query()->where('is_default', true)->value('id'))
                            ->required(),
                    ]),
            ])
            ->action(function (array $data, ImportCloudwaysAppsAction $import): void {
                $token = $data['access_token'] ?? null;
                $token = is_string($token) && $token !== '' ? $token : null;

                try {
                    $result = $import->handle(
                        serverId: (string) $data['server_id'],
                        statusPageId: (int) $data['status_page_id'],
                        accessToken: $token,
                    );
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Import Cloudways fallito')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Import Cloudways completato')
                    ->body(sprintf(
                        'Nuove app: %d. Già presenti (saltate): %d. Collegati a monitor esistenti: %d. Errori: %d.',
                        $result['created'],
                        $result['skipped'],
                        $result['linked'],
                        $result['failed'],
                    ))
                    ->success()
                    ->send();
            });
    }
}
