<?php

namespace App\Filament\Widgets;

use App\Models\Backup;
use Filament\Widgets\ChartWidget;

class BackupStatusWidget extends ChartWidget
{
    protected ?string $heading = 'Backup Status Breakdown';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $pending = Backup::where('status', 'pending')->count();
        $running = Backup::where('status', 'running')->count();
        $completed = Backup::where('status', 'completed')->count();
        $failed = Backup::where('status', 'failed')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Backups by Status',
                    'data' => [$pending, $running, $completed, $failed],
                    'backgroundColor' => [
                        'rgb(156, 163, 175)', // pending - gray
                        'rgb(59, 130, 246)', // running - blue
                        'rgb(34, 197, 94)', // completed - green
                        'rgb(239, 68, 68)', // failed - red
                    ],
                ],
            ],
            'labels' => ['Pending', 'Running', 'Completed', 'Failed'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
