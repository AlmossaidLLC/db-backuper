<?php

namespace App\Filament\Widgets;

use App\Models\Backup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentBackupsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Backup::query()
                    ->with(['connection', 'schedule'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('connection.label')
                    ->label('Connection')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('schedule.name')
                    ->label('Schedule')
                    ->searchable()
                    ->sortable()
                    ->default('Manual')
                    ->placeholder('Manual'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'running' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('file_size_human')
                    ->label('Size')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('file_size', $direction))
                    ->default('Unknown'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not completed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
