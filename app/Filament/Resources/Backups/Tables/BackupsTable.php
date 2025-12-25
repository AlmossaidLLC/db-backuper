<?php

namespace App\Filament\Resources\Backups\Tables;

use App\Models\Backup;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BackupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(50),

                TextColumn::make('connection.label')
                    ->label('Connection')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('schedule.name')
                    ->label('Schedule')
                    ->searchable()
                    ->sortable()
                    ->default('Manual')
                    ->placeholder('Manual'),

                TextColumn::make('status')
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
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('file_size_human')
                    ->label('File Size')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('file_size', $direction))
                    ->default('Unknown'),

                TextColumn::make('completed_at')
                    ->label('Completed At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not completed'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),

                SelectFilter::make('connection_id')
                    ->label('Connection')
                    ->relationship('connection', 'label')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('schedule_id')
                    ->label('Schedule')
                    ->relationship('schedule', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Backup $record): string => route('backups.download', $record))
                    ->visible(fn (Backup $record): bool => $record->status === 'completed' && file_exists(storage_path('app/' . $record->file_path))),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
