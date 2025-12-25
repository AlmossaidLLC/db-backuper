<?php

namespace App\Filament\Resources\Connections\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                                        TextInput::make('db')
                                            ->label('Database Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('my_database'),

                                        TextInput::make('user')
                                            ->label('Username')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('database_user'),
                                    ]),

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
