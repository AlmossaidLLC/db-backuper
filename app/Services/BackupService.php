<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Connection;
use App\Models\Schedule;
use App\Models\Setting;
use App\Services\StorageSettingsService;
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

            // Determine storage driver
            $storageDriver = StorageSettingsService::getStorageDriver();

            // If S3 is configured, upload the backup to S3
            if ($storageDriver === 's3' && StorageSettingsService::isConfigured()) {
                try {
                    // Upload to S3
                    $s3Path = $filePath;
                    $fileContents = file_get_contents($fullPath);

                    if ($fileContents === false) {
                        throw new \Exception('Failed to read backup file');
                    }

                    Log::info('Attempting S3 upload', [
                        'backup_id' => $backup->id,
                        'file_size' => strlen($fileContents),
                        's3_path' => $s3Path,
                    ]);

                    // Get a fresh instance of the S3 disk with updated config
                    // Using Storage::build() to bypass config cache and use direct config
                    $s3Config = [
                        'driver' => 's3',
                        'key' => Setting::get('s3_key'),
                        'secret' => Setting::get('s3_secret'),
                        'region' => Setting::get('s3_region') ?: 'us-east-1',
                        'bucket' => Setting::get('s3_bucket'),
                        'endpoint' => Setting::get('s3_endpoint'),
                        'use_path_style_endpoint' => filter_var(Setting::get('s3_path_style', false), FILTER_VALIDATE_BOOLEAN),
                        'throw' => false,
                        'report' => false,
                        // Add timeout settings to prevent hanging
                        'options' => [
                            'http' => [
                                'timeout' => 60, // 60 seconds timeout
                                'connect_timeout' => 10, // 10 seconds connection timeout
                            ],
                        ],
                    ];

                    Log::debug('S3 config prepared', [
                        'backup_id' => $backup->id,
                        'has_key' => !empty($s3Config['key']),
                        'has_secret' => !empty($s3Config['secret']),
                        'bucket' => $s3Config['bucket'],
                        'endpoint' => $s3Config['endpoint'] ?? 'not set',
                    ]);

                    $s3Disk = Storage::build($s3Config);

                    Log::debug('Storage::build() successful, attempting put', [
                        'backup_id' => $backup->id,
                    ]);

                    // Upload with timeout protection
                    // Set a time limit to prevent hanging
                    $startTime = time();
                    $maxUploadTime = 120; // 2 minutes max for upload

                    set_time_limit($maxUploadTime + 10); // Add buffer

                    $putResult = $s3Disk->put($s3Path, $fileContents);

                    $uploadTime = time() - $startTime;
                    Log::debug('S3 upload completed', [
                        'backup_id' => $backup->id,
                        'upload_time_seconds' => $uploadTime,
                    ]);

                    if (!$putResult) {
                        throw new \Exception('S3 put() returned false - upload may have failed');
                    }

                    Log::info('Backup uploaded to S3 successfully', [
                        'backup_id' => $backup->id,
                        's3_path' => $s3Path,
                    ]);
                } catch (\Exception $s3Exception) {
                    Log::error('Failed to upload backup to S3, keeping local copy', [
                        'backup_id' => $backup->id,
                        'error' => $s3Exception->getMessage(),
                        'trace' => $s3Exception->getTraceAsString(),
                    ]);
                    // Continue with local storage if S3 upload fails
                    $storageDriver = 'local';
                }
            }

            $backup->update([
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'storage_driver' => $storageDriver,
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

        // Add options for consistent backup
        $dumper->addExtraOption('--single-transaction'); // Use transaction for consistent backup
        $dumper->addExtraOption('--quick'); // Retrieve rows for a table from the server a row at a time
        $dumper->addExtraOption('--lock-tables=false'); // Don't lock tables (works with --single-transaction)

        // Note: --skip-column-statistics is NOT added here because:
        // 1. It's only needed when MySQL 8 client dumps a MySQL 5.x server
        // 2. MariaDB's mysqldump/mariadb-dump doesn't support this option
        // If needed in the future, detect MySQL version first before adding this option

        // Enable gzip compression to optimize storage space
        $dumper->useCompressor(new \Spatie\DbDumper\Compressors\GzipCompressor());

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

        // Enable gzip compression to optimize storage space
        $dumper->useCompressor(new \Spatie\DbDumper\Compressors\GzipCompressor());

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
            'mysql' => 'sql.gz',
            'pgsql' => 'sql.gz',
            'sqlite' => 'sqlite',
            default => 'sql.gz',
        };

        return "{$dbName}_{$timestamp}.{$extension}";
    }
}

