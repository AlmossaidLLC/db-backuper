<?php

namespace App\Filament\Resources\Backups\Pages;

use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Backups\Infolists\BackupInfolist;
use App\Models\Backup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewBackup extends ViewRecord
{
    protected static string $resource = BackupResource::class;

    public function infolist(Schema $schema): Schema
    {
        return BackupInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download Backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (Backup $record): string => route('backups.download', $record))
                ->visible(fn (Backup $record): bool => $record->status === 'completed' && file_exists(storage_path('app/' . $record->file_path))),
            DeleteAction::make(),
        ];
    }
}

