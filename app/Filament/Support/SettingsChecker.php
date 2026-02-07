<?php

namespace App\Filament\Support;

use App\Models\Setting;

class SettingsChecker
{
    /**
     * Check if all required settings (SMTP and S3) are configured.
     * This requires actual SMTP and S3 credentials, not just log/local fallbacks.
     */
    public static function isConfigured(): bool
    {
        return self::isSmtpConfigured() && self::isS3Configured();
    }

    /**
     * Check if SMTP mail settings are properly configured.
     * Returns false if using 'log' mailer or if SMTP credentials are missing.
     */
    public static function isMailConfigured(): bool
    {
        return self::isSmtpConfigured();
    }

    /**
     * Check if SMTP is properly configured with all required credentials.
     */
    public static function isSmtpConfigured(): bool
    {
        $mailer = Setting::get('mail_mailer');

        // Must explicitly be set to 'smtp'
        if ($mailer !== 'smtp') {
            return false;
        }

        $host = Setting::get('mail_host');
        $port = Setting::get('mail_port');
        $username = Setting::get('mail_username');
        $password = Setting::get('mail_password');
        $fromAddress = Setting::get('mail_from_address');

        return !empty($host)
            && !empty($port)
            && !empty($username)
            && !empty($password)
            && !empty($fromAddress);
    }

    /**
     * Check if S3 storage settings are properly configured.
     * Returns false if using 'local' storage or if S3 credentials are missing.
     */
    public static function isStorageConfigured(): bool
    {
        return self::isS3Configured();
    }

    /**
     * Check if S3 is properly configured with all required credentials.
     */
    public static function isS3Configured(): bool
    {
        $driver = Setting::get('storage_driver');

        // Must explicitly be set to 's3'
        if ($driver !== 's3') {
            return false;
        }

        $key = Setting::get('s3_key');
        $secret = Setting::get('s3_secret');
        $bucket = Setting::get('s3_bucket');

        return !empty($key)
            && !empty($secret)
            && !empty($bucket);
    }

    /**
     * Get the list of missing settings.
     *
     * @return array<string>
     */
    public static function getMissing(): array
    {
        $missing = [];

        if (!self::isSmtpConfigured()) {
            $missing[] = 'SMTP Mail';
        }

        if (!self::isS3Configured()) {
            $missing[] = 'S3 Storage';
        }

        return $missing;
    }

    /**
     * Get a formatted message about missing settings.
     */
    public static function getMissingMessage(): string
    {
        $missing = self::getMissing();

        if (empty($missing)) {
            return '';
        }

        return 'Please configure ' . implode(' and ', $missing) . ' settings first.';
    }
}
