<?php

namespace App\Filament\Resources\Backups\Infolists;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BackupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Backup Information')
                    ->schema([
                        TextEntry::make('file_name')
                            ->label('File Name')
                            ->copyable(),

                        TextEntry::make('file_path')
                            ->label('File Path')
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('file_size_human')
                            ->label('File Size')
                            ->default('Unknown'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'gray',
                                'running' => 'info',
                                'completed' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Pending',
                                'running' => 'Running',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                default => $state,
                            }),
                    ])
                    ->columns(2),

                Section::make('Connection & Schedule')
                    ->schema([
                        TextEntry::make('connection.label')
                            ->label('Connection'),

                        TextEntry::make('connection.db')
                            ->label('Database'),

                        TextEntry::make('connection.type')
                            ->label('Database Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'mysql' => 'success',
                                'pgsql' => 'info',
                                'sqlite' => 'warning',
                                'sqlsrv' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('schedule.name')
                            ->label('Schedule')
                            ->default('Manual')
                            ->placeholder('Manual'),

                        TextEntry::make('schedule.frequency')
                            ->label('Schedule Frequency')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'hourly' => 'success',
                                'daily' => 'info',
                                'weekly' => 'warning',
                                'monthly' => 'danger',
                                'custom' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                                'hourly' => 'Hourly',
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'custom' => 'Custom',
                                default => null,
                            })
                            ->placeholder('N/A'),
                    ])
                    ->columns(2),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('completed_at')
                            ->label('Completed At')
                            ->dateTime()
                            ->placeholder('Not completed'),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('Error Information')
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Error Message')
                            ->placeholder('No errors')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => !empty($record->error_message)),
            ]);
    }
}

