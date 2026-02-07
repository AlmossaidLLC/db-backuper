<?php

namespace App\Filament\Resources\Connections\Schemas;

use App\Models\Connection;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('Connection Details')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Label')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('My Database Connection')
                                            ->helperText('A friendly name for this connection'),

                                        Select::make('type')
                                            ->label('Database Type')
                                            ->required()
                                            ->options([
                                                'mysql' => 'MySQL',
                                                'pgsql' => 'PostgreSQL',
                                                'sqlite' => 'SQLite',
                                                'sqlsrv' => 'SQL Server',
                                            ])
                                            ->default('mysql')
                                            ->native(false),
                                    ]),
                            ])
                            ->extraAttributes([
                                'class' => 'flex flex-col',
                                'style' => 'height: 100%',
                            ]),

                        Section::make('Server Configuration')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('server')
                                            ->label('Server/Host')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('localhost')
                                            ->helperText('Database server hostname or IP address')
                                            ->columnSpan(2),

                                        TextInput::make('port')
                                            ->label('Port')
                                            ->numeric()
                                            ->maxLength(10)
                                            ->placeholder('3306')
                                            ->helperText('Database port number')
                                            ->default(fn ($get) => match ($get('type')) {
                                                'mysql' => '3306',
                                                'pgsql' => '5432',
                                                'sqlsrv' => '1433',
                                                default => null,
                                            }),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        Select::make('db')
                                            ->label('Database Name')
                                            ->required()
                                            ->searchable()
                                            ->getSearchResultsUsing(function (?string $search, Get $get): array {
                                                $databases = $get('__databases') ?? [];

                                                // If no search term, show all loaded databases
                                                if (empty($search)) {
                                                    return $databases;
                                                }

                                                // Filter databases by search term
                                                $filtered = array_filter(
                                                    $databases,
                                                    fn ($db) => str_contains(strtolower($db), strtolower($search))
                                                );

                                                // Always include the search term as an option (for custom database names)
                                                if (!in_array($search, $filtered)) {
                                                    $filtered = [$search => $search . ' (custom)'] + $filtered;
                                                }

                                                return $filtered;
                                            })
                                            ->getOptionLabelUsing(fn ($value): ?string => $value)
                                            ->placeholder('Type or select database name')
                                            ->helperText('Click "Load Databases" to fetch available databases, or type a name manually')
                                            ->aboveContent(
                                                Action::make('loadDatabases')
                                                    ->label('Load Databases')
                                                    ->icon(Heroicon::ArrowPath)
                                                    ->color('gray')
                                                    ->size('sm')
                                                    ->action(function (Get $schemaGet, \Filament\Schemas\Components\Utilities\Set $schemaSet) {
                                                        // Build a temporary connection to test
                                                        $tempConnection = new Connection();
                                                        $tempConnection->type = $schemaGet('type');
                                                        $tempConnection->server = $schemaGet('server');
                                                        $tempConnection->port = $schemaGet('port');
                                                        $tempConnection->user = $schemaGet('user');
                                                        $tempConnection->password = $schemaGet('password');
                                                        $tempConnection->db = 'information_schema'; // Placeholder

                                                        // Transform extra data from repeater format
                                                        $extra = $schemaGet('extra');
                                                        if (is_array($extra)) {
                                                            $transformed = [];
                                                            foreach ($extra as $item) {
                                                                if (isset($item['key']) && isset($item['value']) && !empty($item['key'])) {
                                                                    $transformed[$item['key']] = $item['value'];
                                                                }
                                                            }
                                                            $tempConnection->extra = $transformed;
                                                        }

                                                        $result = $tempConnection->listDatabases();

                                                        if ($result['success']) {
                                                            $databases = array_combine($result['databases'], $result['databases']);
                                                            $schemaSet('__databases', $databases);

                                                            Notification::make()
                                                                ->title('Databases Loaded')
                                                                ->success()
                                                                ->body('Found ' . count($result['databases']) . ' database(s). Click on the field and type to search.')
                                                                ->send();
                                                        } else {
                                                            Notification::make()
                                                                ->title('Failed to Load Databases')
                                                                ->danger()
                                                                ->body($result['message'])
                                                                ->send();
                                                        }
                                                    })
                                            ),

                                        TextInput::make('user')
                                            ->label('Username')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('database_user'),
                                    ]),

                                TextInput::make('__databases')
                                    ->hidden()
                                    ->dehydrated(false),

                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('password')
                                            ->label('Password')
                                            ->required()
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255)
                                            ->placeholder('••••••••'),
                                    ]),
                            ])
                            ->extraAttributes([
                                'class' => 'flex flex-col',
                                'style' => 'height: 100%',
                            ]),
                    ])
                    ->extraAttributes(['class' => 'items-stretch'])
                    ->columnSpanFull(),

                Section::make('Additional Parameters')
                    ->schema([
                        Repeater::make('extra')
                            ->label('Extra Connection Parameters')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('key')
                                            ->label('Parameter Key')
                                            ->required()
                                            ->placeholder('charset')
                                            ->helperText('e.g., charset, ssl_mode, prefix'),

                                        TextInput::make('value')
                                            ->label('Parameter Value')
                                            ->required()
                                            ->placeholder('utf8mb4'),
                                    ]),
                            ])
                            ->columns(1)
                            ->itemLabel(fn (array $state): ?string => $state['key'] ?? null)
                            ->addActionLabel('Add Parameter')
                            ->collapsible()
                            ->defaultItems(0)
                            ->helperText('Add additional connection parameters if needed (e.g., charset, SSL settings, prefix)'),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
