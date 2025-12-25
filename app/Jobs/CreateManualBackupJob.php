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

    public function __construct(
        public Connection $dbConnection,
        public string $email
    ) {}

    public function handle(BackupService $backupService): void
    {
        try {
            $backup = $backupService->createBackup($this->dbConnection);

            // Dispatch email sending in a separate job to avoid blocking
            // Email failure won't affect backup success
            try {
                \App\Jobs\SendBackupNotificationJob::dispatch($backup, $this->email);
            } catch (\Exception $emailException) {
                // Log but don't fail - backup was successful
                \Illuminate\Support\Facades\Log::warning('Failed to dispatch email notification', [
                    'backup_id' => $backup->id,
                    'error' => $emailException->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            $backup = $this->dbConnection->backups()->latest()->first();

            if ($backup) {
                // Try to send failure notification, but don't fail if it doesn't work
                try {
                    \App\Jobs\SendBackupNotificationJob::dispatch($backup, $this->email, $e->getMessage());
                } catch (\Exception $emailException) {
                    \Illuminate\Support\Facades\Log::warning('Failed to dispatch failure email notification', [
                        'backup_id' => $backup->id,
                        'error' => $emailException->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }
}
