<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Support\SettingsChecker;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

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
