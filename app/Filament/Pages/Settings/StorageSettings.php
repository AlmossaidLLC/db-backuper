<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\SettingsChecker;
use App\Models\Setting;
use App\Services\StorageSettingsService;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StorageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'S3 Storage';

    protected static ?string $title = 'S3 Storage Settings';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.settings.storage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            's3_key' => Setting::get('s3_key'),
            's3_secret' => '',
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
                Section::make('S3-Compatible Storage Configuration')
                    ->description('Configure S3-compatible storage for backup files. Supports AWS S3, MinIO, DigitalOcean Spaces, Backblaze B2, Wasabi, and other S3-compatible services.')
                    ->schema([
                        TextInput::make('s3_key')
                            ->label('Access Key ID')
                            ->placeholder('AKIAIOSFODNN7EXAMPLE')
                            ->maxLength(255)
                            ->required()
                            ->helperText('Your S3-compatible service access key ID'),

                        TextInput::make('s3_secret')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn () => !Setting::has('s3_secret'))
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
                            ->required()
                            ->helperText('Name of your S3 bucket'),

                        TextInput::make('s3_endpoint')
                            ->label('Endpoint URL')
                            ->placeholder('https://s3.amazonaws.com')
                            ->maxLength(255)
                            ->required()
                            ->helperText('S3 endpoint URL. Required for S3-compatible services (e.g., MinIO: http://localhost:9000, DigitalOcean: https://nyc3.digitaloceanspaces.com).'),

                        Toggle::make('s3_path_style')
                            ->label('Use Path Style Endpoint')
                            ->default(true)
                            ->helperText('Keep enabled for most S3-compatible services like MinIO, DigitalOcean Spaces, Wasabi, or Backblaze B2. Disable only for AWS S3 or services that require virtual-hosted bucket URLs.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Get existing secret if not provided
        if (empty($data['s3_secret'])) {
            $data['s3_secret'] = Setting::get('s3_secret');
        }

        // Always use S3
        $data['storage_driver'] = 's3';

        $s3Test = StorageSettingsService::testConnection($data);

        if (!$s3Test['success']) {
            Notification::make()
                ->title('S3 Connection Test Failed')
                ->danger()
                ->body($s3Test['message'])
                ->send();
            return;
        }

        // Only save S3 secret if it was provided (not empty)
        if (empty($data['s3_secret'])) {
            unset($data['s3_secret']);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        // Update storage config dynamically
        StorageSettingsService::configureStorage();

        Notification::make()
            ->title('Storage Settings Saved')
            ->success()
            ->body('S3 connection test passed and settings have been saved.')
            ->send();
    }

    public static function getNavigationBadge(): ?string
    {
        return SettingsChecker::isS3Configured() ? null : '!';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return SettingsChecker::isS3Configured() ? 'success' : 'danger';
    }
}
