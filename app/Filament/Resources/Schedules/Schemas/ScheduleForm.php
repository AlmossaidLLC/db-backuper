<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Models\Connection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                                    ->label('Database Connection')
                                    ->relationship('connection', 'label')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false),

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

                                TextInput::make('notification_email')
                                    ->label('Notification Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->helperText('Email address to receive backup notifications'),

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
