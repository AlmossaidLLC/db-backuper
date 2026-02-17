<?php

namespace App\Filament\Resources\Connections\Tables;

use App\Filament\Resources\Connections\ConnectionResource;
use App\Filament\Support\SettingsChecker;
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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
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
                    ->requiresConfirmation()
                    ->modalHeading('Create Test Backup')
                    ->modalDescription('This will queue a backup of the database. A notification email will be sent to the addresses you specify below.')
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
                    ->action(function (Connection $record, array $data) {
                        // Double-check settings before running backup
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

                        CreateManualBackupJob::dispatch($record, $emails);

                        $emailList = implode(', ', $emails);
                        Notification::make()
                            ->title('Backup Queued Successfully!')
                            ->success()
                            ->body('The backup has been queued and will be processed shortly. Notification will be sent to: ' . $emailList)
                            ->send();
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
                        ->label('Backup Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->disabled(fn (): bool => !SettingsChecker::isConfigured())
                        ->tooltip(fn (): ?string => !SettingsChecker::isConfigured()
                            ? SettingsChecker::getMissingMessage()
                            : null)
                        ->requiresConfirmation()
                        ->modalHeading('Create Bulk Backup')
                        ->modalDescription('This will queue backups for all selected connections. Notification emails will be sent to the addresses you specify below.')
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
                            // Double-check settings before running backups
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
                                CreateManualBackupJob::dispatch($record, $emails);
                                $count++;
                            }

                            $emailList = implode(', ', $emails);
                            Notification::make()
                                ->title('Backups Queued Successfully!')
                                ->success()
                                ->body($count . ' backup(s) have been queued and will be processed shortly. Notification will be sent to: ' . $emailList)
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
