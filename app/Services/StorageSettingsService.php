<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorageSettingsService
{
    public static function isConfigured(): bool
    {
        $driver = Setting::get('storage_driver', 'local');

        if ($driver === 'local') {
            return true; // Local storage is always available
        }

        if ($driver !== 's3') {
            return false;
        }

        $key = Setting::get('s3_key');
        $secret = Setting::get('s3_secret');
        $bucket = Setting::get('s3_bucket');
        $endpoint = Setting::get('s3_endpoint');

        // For S3-compatible services, endpoint is often required
        // For AWS S3, endpoint is optional but key, secret, and bucket are required
        // Region is optional for some S3-compatible services
        return !empty($key)
            && !empty($secret)
            && !empty($bucket);
    }

    public static function getStorageDriver(): string
    {
        $driver = Setting::get('storage_driver', 'local');

        if ($driver === 's3' && self::isConfigured()) {
            return 's3';
        }

        return 'local';
    }

    public static function configureStorage(): void
    {
        $driver = Setting::get('storage_driver', 'local');

        if ($driver === 's3' && self::isConfigured()) {
            Config::set('filesystems.default', 's3');
            Config::set('filesystems.disks.s3.driver', 's3');
            Config::set('filesystems.disks.s3.key', Setting::get('s3_key'));
            Config::set('filesystems.disks.s3.secret', Setting::get('s3_secret'));

            // Region is optional for S3-compatible services
            $region = Setting::get('s3_region');
            if (!empty($region)) {
                Config::set('filesystems.disks.s3.region', $region);
            } else {
                // Default region for AWS S3, but may not be needed for S3-compatible services
                Config::set('filesystems.disks.s3.region', 'us-east-1');
            }

            Config::set('filesystems.disks.s3.bucket', Setting::get('s3_bucket'));

            // Endpoint is required for most S3-compatible services
            $endpoint = Setting::get('s3_endpoint');
            if (!empty($endpoint)) {
                Config::set('filesystems.disks.s3.endpoint', $endpoint);
            }

            // Path style is typically required for S3-compatible services
            $pathStyle = Setting::get('s3_path_style', false);
            Config::set('filesystems.disks.s3.use_path_style_endpoint', filter_var($pathStyle, FILTER_VALIDATE_BOOLEAN));

            // Additional settings for Flysystem v3 compatibility
            Config::set('filesystems.disks.s3.throw', false);
            Config::set('filesystems.disks.s3.report', false);
        } else {
            Config::set('filesystems.default', 'local');
        }
    }

    public static function testConnection(array $settings): array
    {
        try {
            $driver = $settings['storage_driver'] ?? 'local';

            if ($driver === 'local') {
                return [
                    'success' => true,
                    'message' => 'Local storage is available',
                ];
            }

            if ($driver !== 's3') {
                return [
                    'success' => false,
                    'message' => 'Invalid storage driver selected',
                ];
            }

            // Check required fields
            $key = $settings['s3_key'] ?? '';
            $secret = $settings['s3_secret'] ?? '';
            $bucket = $settings['s3_bucket'] ?? '';

            if (empty($key) || empty($secret) || empty($bucket)) {
                return [
                    'success' => false,
                    'message' => 'Missing required S3 credentials (Access Key, Secret Key, or Bucket)',
                ];
            }

            // Build S3 disk configuration
            $s3Config = [
                'driver' => 's3',
                'key' => $key,
                'secret' => $secret,
                'region' => $settings['s3_region'] ?? 'us-east-1',
                'bucket' => $bucket,
                'endpoint' => $settings['s3_endpoint'] ?? null,
                'use_path_style_endpoint' => filter_var($settings['s3_path_style'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'throw' => false,
                'report' => false,
            ];

            // Create a test disk instance
            $s3Disk = Storage::build($s3Config);

            // Test by trying to list the bucket (this will fail if credentials are wrong)
            $s3Disk->files('/', true);

            // Also try to write a test file
            $testFileName = 'test-connection-' . time() . '.txt';
            $testContent = 'Connection test file - can be safely deleted';

            $s3Disk->put($testFileName, $testContent);

            // Clean up test file
            try {
                $s3Disk->delete($testFileName);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }

            return [
                'success' => true,
                'message' => 'S3 connection successful! Bucket is accessible and writable.',
            ];
        } catch (\Exception $e) {
            Log::error('S3 connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = $e->getMessage();

            // Provide more user-friendly error messages
            if (str_contains($errorMessage, 'AccessDenied') || str_contains($errorMessage, '403')) {
                $errorMessage = 'Access denied. Please check your credentials and bucket permissions.';
            } elseif (str_contains($errorMessage, 'NoSuchBucket') || str_contains($errorMessage, '404')) {
                $errorMessage = 'Bucket not found. Please verify the bucket name exists.';
            } elseif (str_contains($errorMessage, 'InvalidAccessKeyId') || str_contains($errorMessage, 'SignatureDoesNotMatch')) {
                $errorMessage = 'Invalid credentials. Please check your Access Key ID and Secret Access Key.';
            } elseif (str_contains($errorMessage, 'Could not resolve host') || str_contains($errorMessage, 'Connection refused')) {
                $errorMessage = 'Cannot connect to S3 endpoint. Please check your endpoint URL and network connectivity.';
            }

            return [
                'success' => false,
                'message' => 'S3 connection failed: ' . $errorMessage,
            ];
        }
    }
}
