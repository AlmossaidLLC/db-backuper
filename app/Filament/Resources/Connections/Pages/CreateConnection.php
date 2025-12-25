<?php

namespace App\Filament\Resources\Connections\Pages;

use App\Filament\Resources\Connections\ConnectionResource;
use App\Models\Connection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateConnection extends CreateRecord
{
    protected static string $resource = ConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test Connection')
                ->icon('heroicon-o-beaker')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Test Database Connection')
                ->modalDescription('This will test the connection with the current form values. Make sure all required fields are filled.')
                ->action(function () {
                    try {
                        $data = $this->form->getState();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Notification::make()
                            ->title('Validation Error')
                            ->danger()
                            ->body('Please fill in all required fields before testing the connection.')
                            ->send();
                        return;
                    }

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
                    $tempConnection = new Connection();
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
        ];
    }
}
