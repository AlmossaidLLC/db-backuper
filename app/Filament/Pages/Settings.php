<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\MailSettingsService;
use App\Services\StorageSettingsService;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected string $view = 'filament.pages.settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mail_mailer' => Setting::get('mail_mailer', 'smtp'),
            'mail_host' => Setting::get('mail_host'),
            'mail_port' => Setting::get('mail_port', '587'),
            'mail_username' => Setting::get('mail_username'),
            'mail_password' => '', // Don't prefill password for security
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address'),
            'mail_from_name' => Setting::get('mail_from_name', config('app.name')),
            'storage_driver' => Setting::get('storage_driver', 'local'),
            's3_key' => Setting::get('s3_key'),
            's3_secret' => '', // Don't prefill secret for security
            's3_region' => Setting::get('s3_region'),
            's3_bucket' => Setting::get('s3_bucket'),
            's3_endpoint' => Setting::get('s3_endpoint'),
            's3_path_style' => Setting::get('s3_path_style', false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SMTP Mail Configuration')
                    ->description('Configure SMTP settings for sending email notifications. If these settings are not configured, email notifications will be disabled.')
                    ->schema([
                        Select::make('mail_mailer')
                            ->label('Mail Driver')
                            ->options([
                                'smtp' => 'SMTP',
                                'log' => 'Log (for testing)',
                            ])
                            ->default('smtp')
                            ->required()
                            ->helperText('Select the mail driver. Use "Log" for testing without sending actual emails.'),

                        TextInput::make('mail_host')
                            ->label('SMTP Host')
                            ->placeholder('smtp.gmail.com')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('mail_mailer') === 'smtp')
                            ->helperText('The SMTP server hostname'),

                        TextInput::make('mail_port')
                            ->label('SMTP Port')
                            ->placeholder('587')
                            ->numeric()
                            ->default('587')
                            ->required(fn ($get) => $get('mail_mailer') === 'smtp')
                            ->helperText('Common ports: 587 (TLS), 465 (SSL), 25 (unencrypted)'),

                        Select::make('mail_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'None',
                            ])
                            ->default('tls')
                            ->required(fn ($get) => $get('mail_mailer') === 'smtp')
                            ->helperText('Encryption method for SMTP connection'),

                        TextInput::make('mail_username')
                            ->label('SMTP Username')
                            ->placeholder('your-email@gmail.com')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('mail_mailer') === 'smtp')
                            ->helperText('Your SMTP username (usually your email address)'),

                        TextInput::make('mail_password')
                            ->label('SMTP Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn ($get) => $get('mail_mailer') === 'smtp' && !Setting::has('mail_password'))
                            ->helperText('Your SMTP password or app-specific password. Leave blank to keep existing password.')
                            ->dehydrated(fn ($state) => filled($state)),

                        TextInput::make('mail_from_address')
                            ->label('From Email Address')
                            ->email()
                            ->placeholder('noreply@example.com')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('mail_mailer') === 'smtp')
                            ->helperText('The email address that will appear as the sender'),

                        TextInput::make('mail_from_name')
                            ->label('From Name')
                            ->placeholder('Database Backup System')
                            ->maxLength(255)
                            ->default(fn () => config('app.name'))
                            ->helperText('The name that will appear as the sender'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('S3-Compatible Storage Configuration')
                    ->description('Configure S3-compatible storage for backup files. Supports AWS S3, MinIO, DigitalOcean Spaces, Backblaze B2, Wasabi, and other S3-compatible services. If not configured, backups will be stored locally.')
                    ->schema([
                        Select::make('storage_driver')
                            ->label('Storage Driver')
                            ->options([
                                'local' => 'Local Storage',
                                's3' => 'S3-Compatible Storage',
                            ])
                            ->default('local')
                            ->required()
                            ->helperText('Select where backup files should be stored. S3-compatible storage requires configuration below.'),

                        TextInput::make('s3_key')
                            ->label('Access Key ID')
                            ->placeholder('AKIAIOSFODNN7EXAMPLE')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('storage_driver') === 's3')
                            ->helperText('Your S3-compatible service access key ID'),

                        TextInput::make('s3_secret')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn ($get) => $get('storage_driver') === 's3' && !Setting::has('s3_secret'))
                            ->helperText('Your S3-compatible service secret access key. Leave blank to keep existing secret.')
                            ->dehydrated(fn ($state) => filled($state)),

                        TextInput::make('s3_region')
                            ->label('Region')
                            ->placeholder('us-east-1 (optional for S3-compatible services)')
                            ->maxLength(255)
                            ->helperText('Region for your S3 bucket. Required for AWS S3 (e.g., us-east-1). Optional for most S3-compatible services like MinIO.'),

                        TextInput::make('s3_bucket')
                            ->label('Bucket Name')
                            ->placeholder('my-backup-bucket')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('storage_driver') === 's3')
                            ->helperText('Name of your S3 bucket'),

                        TextInput::make('s3_endpoint')
                            ->label('Endpoint URL')
                            ->placeholder('https://s3.amazonaws.com')
                            ->maxLength(255)
                            ->helperText('S3 endpoint URL. Required for S3-compatible services (e.g., MinIO: http://localhost:9000, DigitalOcean: https://nyc3.digitaloceanspaces.com). Leave empty for AWS S3.'),

                        Toggle::make('s3_path_style')
                            ->label('Use Path Style Endpoint')
                            ->default(false)
                            ->helperText('Enable for S3-compatible services like MinIO, DigitalOcean Spaces, or Backblaze B2. Required for most non-AWS services.'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Test SMTP connection if SMTP is selected
        if (($data['mail_mailer'] ?? 'smtp') === 'smtp') {
            // Get existing password if not provided
            if (empty($data['mail_password'])) {
                $data['mail_password'] = Setting::get('mail_password');
            }

            $smtpTest = MailSettingsService::testConnection($data);
            
            if (!$smtpTest['success']) {
                Notification::make()
                    ->title('SMTP Connection Test Failed')
                    ->danger()
                    ->body($smtpTest['message'])
                    ->send();
                return;
            }
        }

        // Test S3 connection if S3 is selected
        if (($data['storage_driver'] ?? 'local') === 's3') {
            // Get existing secret if not provided
            if (empty($data['s3_secret'])) {
                $data['s3_secret'] = Setting::get('s3_secret');
            }

            $s3Test = StorageSettingsService::testConnection($data);
            
            if (!$s3Test['success']) {
                Notification::make()
                    ->title('S3 Connection Test Failed')
                    ->danger()
                    ->body($s3Test['message'])
                    ->send();
                return;
            }
        }

        // Only save password if it was provided (not empty)
        // If password is empty, keep the existing one
        if (empty($data['mail_password'])) {
            unset($data['mail_password']);
        }

        // Only save S3 secret if it was provided (not empty)
        if (empty($data['s3_secret'])) {
            unset($data['s3_secret']);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        // Update mail config dynamically
        MailSettingsService::configureMail();

        // Update storage config dynamically
        StorageSettingsService::configureStorage();

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->body('All connection tests passed and settings have been saved.')
            ->send();
    }
}
