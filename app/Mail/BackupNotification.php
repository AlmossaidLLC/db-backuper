<?php

namespace App\Mail;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Backup $backup,
        public ?string $errorMessage = null
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->backup->status === 'completed'
            ? "Database Backup Completed: {$this->backup->connection->label}"
            : "Database Backup Failed: {$this->backup->connection->label}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup-notification',
        );
    }

    public function attachments(): array
    {
        // No attachments - only download link is provided
        return [];
    }
}
