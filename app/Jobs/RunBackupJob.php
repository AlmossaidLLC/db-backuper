<?php

namespace App\Jobs;

use App\Models\Connection;
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
        /** @var Connection|null $connection */
        $connection = $this->schedule->connection;

        if (!$connection) {
            throw new \Exception('Connection not found for schedule');
        }

        try {
            $backup = $backupService->createBackup($connection, $this->schedule);

            $this->schedule->update([
                'last_run_at' => now(),
            ]);

            $this->schedule->calculateNextRun();
            $this->schedule->save();

            if (!empty($this->schedule->notification_emails) && \App\Services\MailSettingsService::isConfigured()) {
                // Email failure won't affect backup success
                foreach ($this->schedule->notification_emails as $email) {
                    try {
                        \App\Jobs\SendBackupNotificationJob::dispatch($backup, $email);
                    } catch (\Exception $emailException) {
                        // Log but don't fail - backup was successful
                        \Illuminate\Support\Facades\Log::warning('Failed to dispatch email notification', [
                            'backup_id' => $backup->id,
                            'schedule_id' => $this->schedule->id,
                            'email' => $email,
                            'error' => $emailException->getMessage(),
                        ]);
                    }
                }
            } elseif (!empty($this->schedule->notification_emails)) {
                \Illuminate\Support\Facades\Log::info('Email notification skipped: SMTP settings not configured', [
                    'backup_id' => $backup->id,
                    'schedule_id' => $this->schedule->id,
                    'emails' => $this->schedule->notification_emails,
                ]);
            }
        } catch (\Exception $e) {
            $failedBackup = $this->schedule->backups()->latest()->first();
            if (!empty($this->schedule->notification_emails) && $failedBackup && \App\Services\MailSettingsService::isConfigured()) {
                // Try to send failure notification, but don't fail if it doesn't work
                foreach ($this->schedule->notification_emails as $email) {
                    try {
                        \App\Jobs\SendBackupNotificationJob::dispatch(
                            $failedBackup,
                            $email,
                            $e->getMessage()
                        );
                    } catch (\Exception $emailException) {
                        \Illuminate\Support\Facades\Log::warning('Failed to dispatch failure email notification', [
                            'backup_id' => $failedBackup->id,
                            'schedule_id' => $this->schedule->id,
                            'email' => $email,
                            'error' => $emailException->getMessage(),
                        ]);
                    }
                }
            }
            throw $e;
        }
    }
}
