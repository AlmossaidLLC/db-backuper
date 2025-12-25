<?php

namespace App\Filament\Widgets;

use App\Models\Backup;
use App\Models\Connection;
use App\Models\Schedule;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalBackups = Backup::count();
        $totalConnections = Connection::count();
        $totalSchedules = Schedule::count();
        $activeSchedules = Schedule::where('is_active', true)->count();
        $completedBackups = Backup::where('status', 'completed')->count();
        $failedBackups = Backup::where('status', 'failed')->count();
        $runningBackups = Backup::where('status', 'running')->count();
        $totalBackupSize = Backup::where('status', 'completed')->sum('file_size') ?? 0;

        return [
            Stat::make('Total Backups', Number::format($totalBackups))
                ->description('All backups created')
                ->descriptionIcon('heroicon-o-circle-stack')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5]),

            Stat::make('Connections', Number::format($totalConnections))
                ->description('Database connections configured')
                ->descriptionIcon('heroicon-o-server')
                ->color('success'),

            Stat::make('Active Schedules', Number::format($activeSchedules))
                ->description("Out of {$totalSchedules} total schedules")
                ->descriptionIcon('heroicon-o-clock')
                ->color($activeSchedules > 0 ? 'success' : 'gray'),

            Stat::make('Completed Backups', Number::format($completedBackups))
                ->description("{$runningBackups} running, {$failedBackups} failed")
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->chart([2, 3, 4, 5, 6, 7, 8]),

            Stat::make('Total Storage Used', $this->formatBytes($totalBackupSize))
                ->description('Storage used by completed backups')
                ->descriptionIcon('heroicon-o-archive-box')
                ->color('info'),
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }
}
