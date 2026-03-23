<?php

namespace App\Filament\Resources\Connections\Tables;

use App\Filament\Resources\Connections\ConnectionResource;
use App\Filament\Support\SettingsChecker;
use App\Jobs\CreateFullServerBackupJob;
use App\Jobs\CreateManualBackupJob;
use App\Models\Connection;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ReplicateAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                    ->sortable()
                    ->placeholder('All (server-level)'),

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
                    ->label('Backup')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->disabled(fn (): bool => !SettingsChecker::isConfigured())
                    ->tooltip(fn (): ?string => !SettingsChecker::isConfigured()
                        ? SettingsChecker::getMissingMessage()
                        : null)
                    ->modalHeading('Create Backup')
                    ->modalDescription('Choose backup scope and notification recipients.')
                    ->form(fn (Connection $record): array => [
                        Radio::make('backup_type')
                            ->label('Backup Scope')
                            ->options([
                                'full' => 'Full Server — back up all databases',
                                'specific' => 'Specific Databases — choose which to back up',
                            ])
                            ->default($record->db ? 'specific' : 'full')
                            ->required()
                            ->live()
                            ->visible($record->type !== 'sqlite'),

                        CheckboxList::make('databases')
                            ->label('Select Databases')
                            ->options(function () use ($record): array {
                                $result = $record->listDatabases();

                                if (!$result['success']) {
                                    return [];
                                }

                                return array_combine($result['databases'], $result['databases']);
                            })
                            ->default($record->db ? [$record->db] : [])
                            ->columns(2)
                            ->bulkToggleable()
                            ->visible(fn (Get $get): bool => $record->type !== 'sqlite' && $get('backup_type') === 'specific')
                            ->helperText('Select one or more databases to back up.'),

                        Repeater::make('emails')
                            ->label('Notification Email Addresses')
                            ->simple(
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->placeholder('email@example.com'),
                            )
                            ->default(fn () => auth()->user()?->email
                                ? [auth()->user()->email]
                                : [])
                            ->addActionLabel('Add Email'),
                    ])
                    ->action(function (Connection $record, array $data) {
                        if (!SettingsChecker::isConfigured()) {
                            Notification::make()
                                ->title('Settings Required')
                                ->warning()
                                ->body(SettingsChecker::getMissingMessage())
                                ->persistent()
                                ->actions([
                                    Action::make('configure')
                                        ->label('Go to Settings')
                                        ->url(\App\Filament\Pages\Settings\SmtpSettings::getUrl())
                                        ->button(),
                                ])
                                ->send();
                            return;
                        }

                        $emails = array_values(array_filter($data['emails'] ?? []));

                        if (empty($emails)) {
                            Notification::make()
                                ->title('No Email Provided')
                                ->warning()
                                ->body('Please provide at least one email address.')
                                ->send();
                            return;
                        }

                        $backupType = $data['backup_type'] ?? 'specific';

                        // SQLite always does a single-db backup
                        if ($record->type === 'sqlite') {
                            CreateManualBackupJob::dispatch($record, $emails, $record->db);

                            Notification::make()
                                ->title('Backup Queued Successfully!')
                                ->success()
                                ->body('The backup has been queued. Notification will be sent to: ' . implode(', ', $emails))
                                ->send();
                            return;
                        }

                        if ($backupType === 'full') {
                            CreateFullServerBackupJob::dispatch($record, $emails);

                            Notification::make()
                                ->title('Full Server Backup Queued!')
                                ->success()
                                ->body('A full backup of all databases on this server has been queued. Notification will be sent to: ' . implode(', ', $emails))
                                ->send();
                        } else {
                            $databases = $data['databases'] ?? [];

                            if (empty($databases)) {
                                Notification::make()
                                    ->title('No Databases Selected')
                                    ->warning()
                                    ->body('Please select at least one database to back up.')
                                    ->send();
                                return;
                            }

                            foreach ($databases as $database) {
                                CreateManualBackupJob::dispatch($record, $emails, $database);
                            }

                            $dbList = implode(', ', $databases);
                            Notification::make()
                                ->title('Backup Queued Successfully!')
                                ->success()
                                ->body(count($databases) . ' backup(s) queued for: ' . $dbList . '. Notification will be sent to: ' . implode(', ', $emails))
                                ->send();
                        }
                    }),
                ReplicateAction::make()
                    ->label('Copy')
                    ->successNotificationTitle('Connection copied')
                    ->successRedirectUrl(fn (Model $replica): string => ConnectionResource::getUrl('edit', ['record' => $replica])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('backup_selected')
                        ->label('Backup All Selected (Full Server)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->disabled(fn (): bool => !SettingsChecker::isConfigured())
                        ->tooltip(fn (): ?string => !SettingsChecker::isConfigured()
                            ? SettingsChecker::getMissingMessage()
                            : null)
                        ->requiresConfirmation()
                        ->modalHeading('Create Bulk Full Server Backup')
                        ->modalDescription('This will queue full server backups for all selected connections. All databases on each server will be backed up.')
                        ->form([
                            Repeater::make('emails')
                                ->label('Notification Email Addresses')
                                ->simple(
                                    TextInput::make('email')
                                        ->email()
                                        ->required()
                                        ->placeholder('email@example.com'),
                                )
                                ->default(fn () => auth()->user()?->email
                                    ? [auth()->user()->email]
                                    : [])
                                ->addActionLabel('Add Email'),
                        ])
                        ->action(function ($records, array $data) {
                            if (!SettingsChecker::isConfigured()) {
                                Notification::make()
                                    ->title('Settings Required')
                                    ->warning()
                                    ->body(SettingsChecker::getMissingMessage())
                                    ->persistent()
                                    ->actions([
                                        Action::make('configure')
                                            ->label('Go to Settings')
                                            ->url(\App\Filament\Pages\Settings\SmtpSettings::getUrl())
                                            ->button(),
                                    ])
                                    ->send();
                                return;
                            }

                            $emails = array_values(array_filter($data['emails'] ?? []));

                            if (empty($emails)) {
                                Notification::make()
                                    ->title('No Email Provided')
                                    ->warning()
                                    ->body('Please provide at least one email address.')
                                    ->send();
                                return;
                            }

                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->type === 'sqlite') {
                                    CreateManualBackupJob::dispatch($record, $emails, $record->db);
                                } else {
                                    CreateFullServerBackupJob::dispatch($record, $emails);
                                }
                                $count++;
                            }

                            $emailList = implode(', ', $emails);
                            Notification::make()
                                ->title('Backups Queued Successfully!')
                                ->success()
                                ->body($count . ' server backup(s) have been queued. Notification will be sent to: ' . $emailList)
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
