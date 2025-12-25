<?php

namespace App\Jobs;

use App\Models\Backup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBackupNotificationJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 120; // 2 minutes for email sending
    public $tries = 3;

    public function __construct(
        public Backup $backup,
        public string $email,
        public ?string $errorMessage = null
    ) {}

    public function handle(): void
    {
        try {
            Mail::to($this->email)
                ->send(new \App\Mail\BackupNotification($this->backup, $this->errorMessage));
        } catch (\Exception $e) {
            // Log email failure but don't fail the job - backup was successful
            \Illuminate\Support\Facades\Log::warning('Failed to send backup notification email', [
                'backup_id' => $this->backup->id,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);
            
            // Don't throw - email failure shouldn't fail the backup job
            // The backup was successful, email is just a notification
        }
    }
}
