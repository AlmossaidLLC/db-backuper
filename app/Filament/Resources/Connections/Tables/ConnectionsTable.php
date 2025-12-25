<?php

namespace App\Filament\Resources\Connections\Tables;

use App\Models\Connection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mysql' => 'success',
                        'pgsql' => 'info',
                        'sqlite' => 'warning',
                        'sqlsrv' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('server')
                    ->label('Server')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->server . ($record->port ? ':' . $record->port : '')),

                TextColumn::make('db')
                    ->label('Database')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user')
                    ->label('Username')
                    ->searchable(),

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
                SelectFilter::make('type')
                    ->label('Database Type')
                    ->options([
                        'mysql' => 'MySQL',
                        'pgsql' => 'PostgreSQL',
                        'sqlite' => 'SQLite',
                        'sqlsrv' => 'SQL Server',
                    ]),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-beaker')
                    ->color('warning')
                    ->action(function (Connection $record) {
                        $result = $record->testConnection();

                        if ($result['success']) {
                            Notification::make()
                                ->title('Connection Successful!')
                                ->success()
                                ->body('The database connection test was successful.')
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Connection Failed')
                                ->danger()
                                ->body($result['message'])
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
