<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Traits\RequiresSettings;
use App\Models\Connection;
use Filament\Resources\Pages\CreateRecord;

class CreateSchedule extends CreateRecord
{
    use RequiresSettings;

    protected static string $resource = ScheduleResource::class;

    public function mount(): void
    {
        if (!$this->guardSettingsOrRedirect()) {
            return;
        }

        parent::mount();
    }

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
        $backupScope = $data['backup_scope'] ?? 'specific';

        if ($backupScope === 'full') {
            $data['database_name'] = null;
        }

        if (empty($data['database_name']) && !empty($data['connection_id'])) {
            $connection = Connection::find($data['connection_id']);
            if ($connection?->db) {
                $data['database_name'] = $connection->db;
            }
        }

        unset($data['backup_scope']);

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
