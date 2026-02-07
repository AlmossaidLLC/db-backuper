<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\SettingsChecker;
use App\Models\Setting;
use App\Services\MailSettingsService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SmtpSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'SMTP Mail';

    protected static ?string $title = 'SMTP Mail Settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.settings.smtp-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mail_host' => Setting::get('mail_host'),
            'mail_port' => Setting::get('mail_port', '587'),
            'mail_username' => Setting::get('mail_username'),
            'mail_password' => '',
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address'),
            'mail_from_name' => Setting::get('mail_from_name', config('app.name')),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SMTP Mail Configuration')
                    ->description('Configure SMTP settings for sending email notifications. Backup notifications will be sent using these settings.')
                    ->schema([
                        TextInput::make('mail_host')
                            ->label('SMTP Host')
                            ->placeholder('smtp.gmail.com')
                            ->maxLength(255)
                            ->required()
                            ->helperText('The SMTP server hostname'),

                        TextInput::make('mail_port')
                            ->label('SMTP Port')
                            ->placeholder('587')
                            ->numeric()
                            ->default('587')
                            ->required()
                            ->helperText('Common ports: 587 (TLS), 465 (SSL), 25 (unencrypted)'),

                        Select::make('mail_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'None',
                            ])
                            ->default('tls')
                            ->required()
                            ->helperText('Encryption method for SMTP connection'),

                        TextInput::make('mail_username')
                            ->label('SMTP Username')
                            ->placeholder('your-email@gmail.com')
                            ->maxLength(255)
                            ->required()
                            ->helperText('Your SMTP username (usually your email address)'),

                        TextInput::make('mail_password')
                            ->label('SMTP Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn () => !Setting::has('mail_password'))
                            ->helperText('Your SMTP password or app-specific password. Leave blank to keep existing password.')
                            ->dehydrated(fn ($state) => filled($state)),

                        TextInput::make('mail_from_address')
                            ->label('From Email Address')
                            ->email()
                            ->placeholder('noreply@example.com')
                            ->maxLength(255)
                            ->required()
                            ->helperText('The email address that will appear as the sender'),

                        TextInput::make('mail_from_name')
                            ->label('From Name')
                            ->placeholder('Database Backup System')
                            ->maxLength(255)
                            ->default(fn () => config('app.name'))
                            ->visible(fn ($get) => $get('mail_mailer') === 'smtp')
                            ->helperText('The name that will appear as the sender'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Get existing password if not provided
        if (empty($data['mail_password'])) {
            $data['mail_password'] = Setting::get('mail_password');
        }

        // Always use SMTP
        $data['mail_mailer'] = 'smtp';

        $smtpTest = MailSettingsService::testConnection($data);

        if (!$smtpTest['success']) {
            Notification::make()
                ->title('SMTP Connection Test Failed')
                ->danger()
                ->body($smtpTest['message'])
                ->send();
            return;
        }

        // Only save password if it was provided (not empty)
        if (empty($data['mail_password'])) {
            unset($data['mail_password']);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        // Update mail config dynamically
        MailSettingsService::configureMail();

        Notification::make()
            ->title('SMTP Settings Saved')
            ->success()
            ->body('SMTP connection test passed and settings have been saved.')
            ->send();
    }

    public static function getNavigationBadge(): ?string
    {
        return SettingsChecker::isSmtpConfigured() ? null : '!';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return SettingsChecker::isSmtpConfigured() ? 'success' : 'danger';
    }
}
