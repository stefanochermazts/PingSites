<?php

namespace App\Filament\Pages;

use App\Settings\MonitorSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $title = 'Impostazioni globali';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 99;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(MonitorSettings::class);

        $this->form->fill([
            'status_page_title' => $settings->status_page_title,
            'alert_recipients' => $settings->alert_recipients,
            'mail_from_address' => $settings->mail_from_address,
            'mail_from_name' => $settings->mail_from_name,
            'default_check_frequency' => $settings->default_check_frequency,
            'default_timeout' => $settings->default_timeout,
            'default_valid_status_codes' => $settings->default_valid_status_codes,
            'default_failure_threshold' => $settings->default_failure_threshold,
            'default_recovery_threshold' => $settings->default_recovery_threshold,
            'check_retention_days' => $settings->check_retention_days,
            'notification_log_retention_days' => $settings->notification_log_retention_days,
            'user_agent' => $settings->user_agent,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Status page')
                        ->schema([
                            TextInput::make('status_page_title')
                                ->label('Titolo status page')
                                ->required(),
                        ]),
                    Section::make('Email alert')
                        ->columns(2)
                        ->schema([
                            TextInput::make('alert_recipients')
                                ->label('Destinatari (separati da virgola)')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('mail_from_address')
                                ->label('Email mittente')
                                ->email()
                                ->required(),
                            TextInput::make('mail_from_name')
                                ->label('Nome mittente')
                                ->required(),
                        ]),
                    Section::make('Default monitor')
                        ->columns(2)
                        ->schema([
                            TextInput::make('default_check_frequency')
                                ->label('Frequenza default (min)')
                                ->numeric()
                                ->required(),
                            TextInput::make('default_timeout')
                                ->label('Timeout default (s)')
                                ->numeric()
                                ->required(),
                            TagsInput::make('default_valid_status_codes')
                                ->label('Codici HTTP validi default')
                                ->required(),
                            TextInput::make('default_failure_threshold')
                                ->label('Fallimenti default')
                                ->numeric()
                                ->required(),
                            TextInput::make('default_recovery_threshold')
                                ->label('Successi default')
                                ->numeric()
                                ->required(),
                            TextInput::make('user_agent')
                                ->label('User-Agent')
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    Section::make('Retention')
                        ->columns(2)
                        ->schema([
                            TextInput::make('check_retention_days')
                                ->label('Retention check (giorni)')
                                ->numeric()
                                ->required(),
                            TextInput::make('notification_log_retention_days')
                                ->label('Retention log notifiche (giorni)')
                                ->numeric()
                                ->required(),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Salva impostazioni')
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('form'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['default_valid_status_codes'] = array_map('intval', $data['default_valid_status_codes']);

        $settings = app(MonitorSettings::class);
        $settings->fill($data);
        $settings->save();

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }
}
