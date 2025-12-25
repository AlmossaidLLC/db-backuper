<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

class QueueJobsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $pendingJobs = 0;
        $processingJobs = 0;
        $failedJobs = 0;

        // Check if jobs table exists (database queue driver)
        if (Schema::hasTable('jobs')) {
            try {
                $pendingJobs = DB::table('jobs')
                    ->whereNull('reserved_at')
                    ->count();
                
                $processingJobs = DB::table('jobs')
                    ->whereNotNull('reserved_at')
                    ->count();
            } catch (\Exception $e) {
                // Table might not be accessible
            }
        }

        // Check if failed_jobs table exists
        if (Schema::hasTable('failed_jobs')) {
            try {
                $failedJobs = DB::table('failed_jobs')->count();
            } catch (\Exception $e) {
                // Table might not be accessible
            }
        }

        return [
            Stat::make('Pending Jobs', Number::format($pendingJobs))
                ->description('Jobs waiting in queue')
                ->descriptionIcon('heroicon-o-clock')
                ->color($pendingJobs > 0 ? 'warning' : 'success'),

            Stat::make('Processing Jobs', Number::format($processingJobs))
                ->description('Jobs currently being processed')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('info'),

            Stat::make('Failed Jobs', Number::format($failedJobs))
                ->description('Jobs that failed to execute')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($failedJobs > 0 ? 'danger' : 'success'),
        ];
    }
}
