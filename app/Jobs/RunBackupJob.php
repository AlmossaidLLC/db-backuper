<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Retry 3 times on failure

    public function __construct(
        public Schedule $schedule
    ) {}

    public function handle(BackupService $backupService): void
    {
        $connection = $this->schedule->connection;

        try {
            $backup = $backupService->createBackup($connection, $this->schedule);

            $this->schedule->update([
                'last_run_at' => now(),
            ]);

            $this->schedule->calculateNextRun();
            $this->schedule->save();

            if ($this->schedule->notification_email) {
                // Email failure won't affect backup success
                try {
                    \App\Jobs\SendBackupNotificationJob::dispatch($backup, $this->schedule->notification_email);
                } catch (\Exception $emailException) {
                    // Log but don't fail - backup was successful
                    \Illuminate\Support\Facades\Log::warning('Failed to dispatch email notification', [
                        'backup_id' => $backup->id,
                        'schedule_id' => $this->schedule->id,
                        'error' => $emailException->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            $failedBackup = $this->schedule->backups()->latest()->first();
            if ($this->schedule->notification_email && $failedBackup) {
                // Try to send failure notification, but don't fail if it doesn't work
                try {
                    \App\Jobs\SendBackupNotificationJob::dispatch(
                        $failedBackup,
                        $this->schedule->notification_email,
                        $e->getMessage()
                    );
                } catch (\Exception $emailException) {
                    \Illuminate\Support\Facades\Log::warning('Failed to dispatch failure email notification', [
                        'backup_id' => $failedBackup->id,
                        'schedule_id' => $this->schedule->id,
                        'error' => $emailException->getMessage(),
                    ]);
                }
            }
            throw $e;
        }
    }
}
