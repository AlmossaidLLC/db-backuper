<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    protected $fillable = [
        'connection_id',
        'name',
        'cron_expression',
        'frequency',
        'is_active',
        'notification_emails',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'notification_emails' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function shouldRun(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->next_run_at) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->next_run_at);
    }

    public function calculateNextRun(): void
    {
        if ($this->frequency === 'custom') {
            $this->next_run_at = $this->getNextRunFromCron();
        } else {
            $this->next_run_at = match ($this->frequency) {
                'hourly' => now()->addHour(),
                'daily' => now()->addDay(),
                'weekly' => now()->addWeek(),
                'monthly' => now()->addMonth(),
                default => now()->addDay(),
            };
        }
    }

    protected function getNextRunFromCron(): \Carbon\Carbon
    {
        $parts = explode(' ', $this->cron_expression);

        if (count($parts) !== 5) {
            return now()->addDay();
        }

        [$minute, $hour, $day, $month, $dayOfWeek] = $parts;

        $now = now();
        $nextRun = $now->copy();

        if ($minute !== '*') {
            $nextRun->minute((int) $minute);
        }
        if ($hour !== '*') {
            $nextRun->hour((int) $hour);
        }
        if ($day !== '*') {
            $nextRun->day((int) $day);
        }
        if ($month !== '*') {
            $nextRun->month((int) $month);
        }

        if ($nextRun->lessThan($now)) {
            $nextRun->addDay();
        }

        return $nextRun;
    }
}
