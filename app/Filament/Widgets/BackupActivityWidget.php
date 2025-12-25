<?php

namespace App\Filament\Widgets;

use App\Models\Backup;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class BackupActivityWidget extends ChartWidget
{
    protected ?string $heading = 'Backup Activity (Last 7 Days)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $days = [];
        $completedData = [];
        $failedData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $days[] = $date->format('M d');

            $completedData[] = Backup::where('status', 'completed')
                ->whereDate('created_at', $date->toDateString())
                ->count();

            $failedData[] = Backup::where('status', 'failed')
                ->whereDate('created_at', $date->toDateString())
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Completed',
                    'data' => $completedData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Failed',
                    'data' => $failedData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
