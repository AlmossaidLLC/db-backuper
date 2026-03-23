<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Models\Schedule;
use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 1800; // 30 minutes timeout (full server backups can be large)
    public $tries = 1; // Don't retry to avoid duplicate backups

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
            // Prefer schedule-specific target DB when configured.
            // Fallback to connection->db for backward compatibility with existing schedules.
            $targetDatabase = $this->schedule->database_name ?: $connection->db;

            if ($targetDatabase) {
                $backups = [$backupService->createBackup($connection, $this->schedule, $targetDatabase)];
            } else {
                $backups = $backupService->createServerBackup($connection);

                // Associate the schedule with each backup
                foreach ($backups as $backup) {
                    $backup->update(['schedule_id' => $this->schedule->id]);
                }
            }

            $this->schedule->update([
                'last_run_at' => now(),
            ]);

            $this->schedule->calculateNextRun();
            $this->schedule->save();

            if (!empty($this->schedule->notification_emails) && \App\Services\MailSettingsService::isConfigured()) {
                foreach ($backups as $backup) {
                    foreach ($this->schedule->notification_emails as $email) {
                        try {
                            \App\Jobs\SendBackupNotificationJob::dispatch($backup, $email);
                        } catch (\Exception $emailException) {
                            \Illuminate\Support\Facades\Log::warning('Failed to dispatch email notification', [
                                'backup_id' => $backup->id,
                                'schedule_id' => $this->schedule->id,
                                'email' => $email,
                                'error' => $emailException->getMessage(),
                            ]);
                        }
                    }
                }
            } elseif (!empty($this->schedule->notification_emails)) {
                \Illuminate\Support\Facades\Log::info('Email notification skipped: SMTP settings not configured', [
                    'schedule_id' => $this->schedule->id,
                    'emails' => $this->schedule->notification_emails,
                    'backup_count' => count($backups),
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
