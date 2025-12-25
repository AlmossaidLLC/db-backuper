<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Resources\Schedules\ScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSchedule extends CreateRecord
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

    protected function mutateFormDataBeforeCreate(array $data): array
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

        $schedule = new \App\Models\Schedule();
        $schedule->fill($data);
        $schedule->calculateNextRun();

        return $schedule->toArray();
    }
}
