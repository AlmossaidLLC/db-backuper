<?php

namespace App\Filament\Resources\Connections\Tables;

use App\Jobs\CreateManualBackupJob;
use App\Models\Connection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
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
                    ->label('Test Connection')
                    ->icon('heroicon-o-beaker')
                    ->color('success')
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
                Action::make('backup')
                    ->label('Create Backup')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Create Test Backup')
                    ->modalDescription('This will queue a backup of the database. You will receive an email notification when the backup is complete.')
                    ->form([
                        TagsInput::make('emails')
                            ->label('Email Addresses')
                            ->required()
                            ->placeholder('Add email and press Enter')
                            ->splitKeys(['Tab', ',', ' '])
                            ->nestedRecursiveRules(['email'])
                            ->default(fn () => auth()->user()?->email ? [auth()->user()->email] : [])
                            ->helperText('Email addresses to receive backup notification. Press Enter, Tab, comma, or space to add multiple emails.'),
                    ])
                    ->action(function (Connection $record, array $data) {
                        $emails = array_filter($data['emails'] ?? []);

                        if (empty($emails)) {
                            Notification::make()
                                ->title('No Email Provided')
                                ->warning()
                                ->body('Please provide at least one email address.')
                                ->send();
                            return;
                        }

                        CreateManualBackupJob::dispatch($record, $emails);

                        $emailList = implode(', ', $emails);
                        Notification::make()
                            ->title('Backup Queued Successfully!')
                            ->success()
                            ->body('The backup has been queued and will be processed shortly. Notification will be sent to: ' . $emailList)
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
