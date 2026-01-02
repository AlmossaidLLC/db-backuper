<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class CreateManualBackupJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Retry 3 times on failure

    /**
     * @param array<string> $emails
     */
    public function __construct(
        public Connection $dbConnection,
        public array $emails
    ) {}

    public function handle(BackupService $backupService): void
    {
        try {
            $backup = $backupService->createBackup($this->dbConnection);

            // Dispatch email sending in a separate job to avoid blocking
            // Email failure won't affect backup success
            if (\App\Services\MailSettingsService::isConfigured() && !empty($this->emails)) {
                foreach ($this->emails as $email) {
                    try {
                        \App\Jobs\SendBackupNotificationJob::dispatch($backup, $email);
                    } catch (\Exception $emailException) {
                        // Log but don't fail - backup was successful
                        \Illuminate\Support\Facades\Log::warning('Failed to dispatch email notification', [
                            'backup_id' => $backup->id,
                            'email' => $email,
                            'error' => $emailException->getMessage(),
                        ]);
                    }
                }
            } elseif (!empty($this->emails)) {
                \Illuminate\Support\Facades\Log::info('Email notification skipped: SMTP settings not configured', [
                    'backup_id' => $backup->id,
                    'emails' => $this->emails,
                ]);
            }
        } catch (\Exception $e) {
            $backup = $this->dbConnection->backups()->latest()->first();

            if ($backup && \App\Services\MailSettingsService::isConfigured() && !empty($this->emails)) {
                // Try to send failure notification, but don't fail if it doesn't work
                foreach ($this->emails as $email) {
                    try {
                        \App\Jobs\SendBackupNotificationJob::dispatch($backup, $email, $e->getMessage());
                    } catch (\Exception $emailException) {
                        \Illuminate\Support\Facades\Log::warning('Failed to dispatch failure email notification', [
                            'backup_id' => $backup->id,
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
