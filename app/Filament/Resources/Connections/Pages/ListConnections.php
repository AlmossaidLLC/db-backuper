<?php

namespace App\Filament\Resources\Connections\Pages;

use App\Filament\Resources\Connections\ConnectionResource;
use App\Filament\Support\SettingsChecker;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnections extends ListRecords
{
    protected static string $resource = ConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->disabled(fn (): bool => !SettingsChecker::isConfigured())
                ->tooltip(fn (): ?string => !SettingsChecker::isConfigured()
                    ? SettingsChecker::getMissingMessage()
                    : null),
        ];
    }
}
