<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Resources\Schedules\ScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchedule extends EditRecord
{
    protected static string $resource = ScheduleResource::class;

    protected function getFormMaxWidth(): ?string
    {
        return 'full';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['frequency'] !== 'custom') {
            $data['cron_expression'] = match ($data['frequency']) {
                'hourly' => '0 * * * *',
                'daily' => '0 0 * * *',
                'weekly' => '0 0 * * 0',
                'monthly' => '0 0 1 * *',
                default => '0 0 * * *',
            };
        }

        $schedule = $this->record;
        $schedule->fill($data);
        $schedule->calculateNextRun();

        return $schedule->toArray();
    }
}
