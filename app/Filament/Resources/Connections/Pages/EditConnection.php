<?php

namespace App\Filament\Resources\Connections\Pages;

use App\Filament\Resources\Connections\ConnectionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditConnection extends EditRecord
{
    protected static string $resource = ConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test Connection')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->action(function () {
                    // Use current form data if modified, otherwise use saved data
                    $data = $this->form->getState();
                    
                    // Transform extra data from repeater format to flat array
                    if (isset($data['extra']) && is_array($data['extra'])) {
                        $transformed = [];
                        foreach ($data['extra'] as $item) {
                            if (isset($item['key']) && isset($item['value']) && !empty($item['key'])) {
                                $transformed[$item['key']] = $item['value'];
                            }
                        }
                        $data['extra'] = $transformed;
                    }
                    
                    // Create a temporary connection instance for testing
                    $tempConnection = new \App\Models\Connection();
                    $tempConnection->fill($data);
                    
                    $result = $tempConnection->testConnection();
                    
                    if ($result['success']) {
                        Notification::make()
                            ->title('Connection Successful!')
                            ->success()
                            ->body('The database connection test was successful.')
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Connection Failed')
                            ->danger()
                            ->body($result['message'])
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
