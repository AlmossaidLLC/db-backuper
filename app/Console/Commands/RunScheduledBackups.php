<?php

namespace App\Console\Commands;

use App\Jobs\RunBackupJob;
use App\Models\Schedule;
use Illuminate\Console\Command;

class RunScheduledBackups extends Command
{
    protected $signature = 'backups:run-scheduled';

    protected $description = 'Run scheduled database backups';

    public function handle(): int
    {
        $schedules = Schedule::where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No scheduled backups to run.');
            return self::SUCCESS;
        }

        $this->info("Found {$schedules->count()} schedule(s) to run.");

        foreach ($schedules as $schedule) {
            if ($schedule->shouldRun()) {
                $this->info("Dispatching backup job for schedule: {$schedule->name}");
                RunBackupJob::dispatch($schedule);
            }
        }

        $this->info('All scheduled backups have been dispatched.');
        return self::SUCCESS;
    }
}
