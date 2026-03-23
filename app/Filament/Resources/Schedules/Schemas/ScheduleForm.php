<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Models\Connection;
use App\Models\Schedule;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema([
                        Section::make('Schedule Details')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Schedule Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Daily Backup')
                                    ->helperText('A friendly name for this schedule'),

                                Select::make('connection_id')
                                    ->label('Server Connection')
                                    ->relationship('connection', 'label')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('database_name', null);
                                    })
                                    ->native(false)
                                    ->helperText('Choose a server connection, then define whether this schedule targets one database or all databases.'),

                                Radio::make('backup_scope')
                                    ->label('Backup Scope')
                                    ->options([
                                        'specific' => 'Specific Database',
                                        'full' => 'Full Server (All Databases)',
                                    ])
                                    ->default(function (?Schedule $record, Get $get): string {
                                        if ($record?->database_name) {
                                            return 'specific';
                                        }

                                        $connectionId = $get('connection_id') ?? $record?->connection_id;
                                        if (!$connectionId) {
                                            return 'specific';
                                        }

                                        $connection = Connection::find($connectionId);

                                        return $connection?->db ? 'specific' : 'full';
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (string $state, callable $set) {
                                        if ($state === 'full') {
                                            $set('database_name', null);
                                        }
                                    })
                                    ->required(),

                                Select::make('database_name')
                                    ->label('Database')
                                    ->options(function (Get $get): array {
                                        $connectionId = $get('connection_id');

                                        if (!$connectionId) {
                                            return [];
                                        }

                                        $connection = Connection::find($connectionId);

                                        if (!$connection) {
                                            return [];
                                        }

                                        // SQLite always has a single file/database target.
                                        if ($connection->type === 'sqlite') {
                                            return $connection->db ? [$connection->db => $connection->db] : [];
                                        }

                                        if ($connection->db) {
                                            return [$connection->db => $connection->db];
                                        }

                                        $result = $connection->listDatabases();

                                        if (!($result['success'] ?? false)) {
                                            return [];
                                        }

                                        $databases = $result['databases'] ?? [];

                                        return array_combine($databases, $databases) ?: [];
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get): bool => $get('backup_scope') !== 'full')
                                    ->visible(fn (Get $get): bool => $get('backup_scope') !== 'full')
                                    ->native(false)
                                    ->helperText('Select the database to back up when using specific scope.'),

                                Select::make('frequency')
                                    ->label('Frequency')
                                    ->options([
                                        'hourly' => 'Hourly',
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly',
                                        'custom' => 'Custom (Cron Expression)',
                                    ])
                                    ->default('daily')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state !== 'custom') {
                                            $set('cron_expression', self::getDefaultCronExpression($state));
                                        }
                                    }),

                                TextInput::make('cron_expression')
                                    ->label('Cron Expression')
                                    ->required()
                                    ->placeholder('0 0 * * *')
                                    ->helperText('Format: minute hour day month day-of-week (e.g., 0 0 * * * for daily at midnight)')
                                    ->visible(fn (callable $get) => $get('frequency') === 'custom')
                                    ->default('0 0 * * *'),

                                TagsInput::make('notification_emails')
                                    ->label('Notification Emails')
                                    ->placeholder('Add email and press Enter')
                                    ->splitKeys(['Tab', ',', ' '])
                                    ->nestedRecursiveRules(['email'])
                                    ->helperText('Email addresses to receive backup notifications. Press Enter, Tab, comma, or space to add multiple emails.'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Enable or disable this schedule'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function getDefaultCronExpression(string $frequency): string
    {
        return match ($frequency) {
            'hourly' => '0 * * * *',
            'daily' => '0 0 * * *',
            'weekly' => '0 0 * * 0',
            'monthly' => '0 0 1 * *',
            default => '0 0 * * *',
        };
    }
}
