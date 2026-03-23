<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateFullServerBackupJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 1800; // 30 minutes timeout for full server backup
    public $tries = 1; // Don't retry full server backups to avoid duplicates

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
            $backups = $backupService->createServerBackup($this->dbConnection);

            if (\App\Services\MailSettingsService::isConfigured() && !empty($this->emails)) {
                foreach ($backups as $backup) {
                    foreach ($this->emails as $email) {
                        try {
                            SendBackupNotificationJob::dispatch($backup, $email);
                        } catch (\Exception $emailException) {
                            Log::warning('Failed to dispatch email notification for full server backup', [
                                'backup_id' => $backup->id,
                                'email' => $email,
                                'error' => $emailException->getMessage(),
                            ]);
                        }
                    }
                }
            } elseif (!empty($this->emails)) {
                Log::info('Email notification skipped: SMTP settings not configured', [
                    'connection_id' => $this->dbConnection->id,
                    'emails' => $this->emails,
                    'backup_count' => count($backups),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Full server backup failed', [
                'connection_id' => $this->dbConnection->id,
                'error' => $e->getMessage(),
            ]);

            if (\App\Services\MailSettingsService::isConfigured() && !empty($this->emails)) {
                foreach ($this->emails as $email) {
                    try {
                        // Try to find the last failed backup for notification
                        $failedBackup = $this->dbConnection->backups()->latest()->first();
                        if ($failedBackup) {
                            SendBackupNotificationJob::dispatch($failedBackup, $email, $e->getMessage());
                        }
                    } catch (\Exception $emailException) {
                        Log::warning('Failed to dispatch failure email notification', [
                            'connection_id' => $this->dbConnection->id,
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
