<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Connection;
use App\Models\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Spatie\DbDumper\DbDumper;

class BackupService
{
    public function __construct()
    {
        // Ensure autoloader is loaded in queue context
        $autoloadPath = base_path('vendor/autoload.php');
        if (file_exists($autoloadPath) && !class_exists(\Spatie\DbDumper\DbDumper::class)) {
            require_once $autoloadPath;
        }
    }

    public function createBackup(Connection $connection, ?Schedule $schedule = null): Backup
    {
        $backup = Backup::create([
            'connection_id' => $connection->id,
            'schedule_id' => $schedule?->id,
            'file_path' => '',
            'file_name' => '',
            'status' => 'pending',
        ]);

        try {
            $backup->status = 'running';
            $backup->save();

            $dumper = $this->createDumper($connection);
            $fileName = $this->generateFileName($connection);
            $filePath = 'backups/' . $fileName;

            $fullPath = storage_path('app/' . $filePath);
            $directory = dirname($fullPath);

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $dumper->dumpToFile($fullPath);

            $fileSize = file_exists($fullPath) ? filesize($fullPath) : null;

            $backup->update([
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $backup;
        } catch (\Exception $e) {
            Log::error('Backup failed', [
                'connection_id' => $connection->id,
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            $backup->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function createDumper(Connection $connection): DbDumper
    {
        $config = $connection->getConnectionConfig();
        $extra = $connection->getExtraAsArray();

        return match ($connection->type) {
            'mysql' => $this->createMySqlDumper($connection, $config, $extra),
            'pgsql' => $this->createPostgreSqlDumper($connection, $config, $extra),
            'sqlite' => $this->createSqliteDumper($connection, $config, $extra),
            default => throw new \Exception("Unsupported database type: {$connection->type}"),
        };
    }

    protected function createMySqlDumper(Connection $connection, array $config, array $extra): MySql
    {
        // Ensure autoloader is loaded
        if (!class_exists(\Spatie\DbDumper\Databases\MySql::class)) {
            $autoloadPath = base_path('vendor/autoload.php');
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }

            // Try again after loading autoloader
            if (!class_exists(\Spatie\DbDumper\Databases\MySql::class)) {
                throw new \RuntimeException(
                    'Spatie DB Dumper MySQL class not found. Please ensure vendor directory is accessible and run "composer dump-autoload" in your Docker container.'
                );
            }
        }

        $dumper = MySql::create()
            ->setDbName($connection->db)
            ->setUserName($connection->user)
            ->setPassword($connection->password);

        if ($connection->server) {
            $dumper->setHost($connection->server);
        }

        if ($connection->port) {
            $dumper->setPort((int) $connection->port);
        }

        if (isset($extra['socket'])) {
            $dumper->setSocket($extra['socket']);
        }

        return $dumper;
    }

    protected function createPostgreSqlDumper(Connection $connection, array $config, array $extra): PostgreSql
    {
        // Ensure autoloader is loaded
        if (!class_exists(\Spatie\DbDumper\Databases\PostgreSql::class)) {
            $autoloadPath = base_path('vendor/autoload.php');
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }
        }

        $dumper = PostgreSql::create()
            ->setDbName($connection->db)
            ->setUserName($connection->user)
            ->setPassword($connection->password);

        if ($connection->server) {
            $dumper->setHost($connection->server);
        }

        if ($connection->port) {
            $dumper->setPort((int) $connection->port);
        }

        return $dumper;
    }

    protected function createSqliteDumper(Connection $connection, array $config, array $extra): Sqlite
    {
        // Ensure autoloader is loaded
        if (!class_exists(\Spatie\DbDumper\Databases\Sqlite::class)) {
            $autoloadPath = base_path('vendor/autoload.php');
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }
        }

        return Sqlite::create()
            ->setDbName($connection->db);
    }

    protected function generateFileName(Connection $connection): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $dbName = Str::slug($connection->db);
        $extension = match ($connection->type) {
            'mysql' => 'sql',
            'pgsql' => 'sql',
            'sqlite' => 'sqlite',
            default => 'sql',
        };

        return "{$dbName}_{$timestamp}.{$extension}";
    }
}

