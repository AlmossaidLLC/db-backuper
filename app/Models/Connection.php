<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Connection extends Model
{
    protected $fillable = [
        'label',
        'server',
        'port',
        'type',
        'user',
        'password',
        'db',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
        ];
    }

    public function setExtraAttribute($value): void
    {
        // Transform repeater data from [['key' => 'port', 'value' => '3306']] to ['port' => '3306']
        if (is_array($value) && !empty($value)) {
            $transformed = [];
            foreach ($value as $item) {
                if (isset($item['key']) && isset($item['value']) && !empty($item['key'])) {
                    $transformed[$item['key']] = $item['value'];
                }
            }
            $this->attributes['extra'] = !empty($transformed) ? json_encode($transformed) : null;
        } else {
            $this->attributes['extra'] = $value ? json_encode($value) : null;
        }
    }

    public function getExtraAttribute($value): ?array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        // Transform from ['port' => '3306'] to [['key' => 'port', 'value' => '3306']] for repeater
        $transformed = [];
        foreach ($decoded as $key => $val) {
            $transformed[] = [
                'key' => $key,
                'value' => $val,
            ];
        }

        return $transformed;
    }

    public function getExtraAsArray(): array
    {
        $value = $this->attributes['extra'] ?? null;

        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function testConnection(): array
    {
        try {
            $config = $this->getConnectionConfig();

            // Create a temporary connection
            config(['database.connections.test_connection' => $config]);

            // Test the connection
            DB::connection('test_connection')->getPdo();

            // Clean up
            DB::purge('test_connection');

            return [
                'success' => true,
                'message' => 'Connection successful!',
            ];
        } catch (\Exception $e) {
            Log::error('Connection test failed', [
                'connection_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function getConnectionConfig(): array
    {
        $config = [
            'driver' => $this->type,
        ];

        // SQLite uses database as file path, not host/user/password
        if ($this->type === 'sqlite') {
            $config['database'] = $this->db;
        } else {
            $config['host'] = $this->server;
            $config['database'] = $this->db;
            $config['username'] = $this->user;
            $config['password'] = $this->password;

            // Add port if specified
            if ($this->port) {
                $config['port'] = $this->port;
            }

            // Set defaults for common database types
            if ($this->type === 'mysql') {
                $config['charset'] = 'utf8mb4';
                $config['collation'] = 'utf8mb4_unicode_ci';
                $config['strict'] = true;
                $config['engine'] = null;
                // Default MySQL port if not specified
                if (!isset($config['port'])) {
                    $config['port'] = '3306';
                }
            } elseif ($this->type === 'pgsql') {
                $config['charset'] = 'utf8';
                // Default PostgreSQL port if not specified
                if (!isset($config['port'])) {
                    $config['port'] = '5432';
                }
            } elseif ($this->type === 'sqlsrv') {
                // Default SQL Server port if not specified
                if (!isset($config['port'])) {
                    $config['port'] = '1433';
                }
            }
        }

        // Merge extra attributes if they exist (these override defaults)
        $extra = $this->getExtraAsArray();
        if (!empty($extra)) {
            $config = array_merge($config, $extra);
        }

        return $config;
    }
}
