<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailSettingsService
{
    public static function isConfigured(): bool
    {
        $mailer = Setting::get('mail_mailer', 'log');

        if ($mailer === 'log') {
            return true; // Log mailer doesn't need credentials
        }

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

    public static function configureMail(): void
    {
        $mailer = Setting::get('mail_mailer', 'log');

        if ($mailer === 'log') {
            Config::set('mail.default', 'log');
            return;
        }

        if ($mailer === 'smtp' && self::isConfigured()) {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', Setting::get('mail_host'));
            Config::set('mail.mailers.smtp.port', Setting::get('mail_port', '587'));

            $encryption = Setting::get('mail_encryption', 'tls');
            Config::set('mail.mailers.smtp.encryption', !empty($encryption) ? $encryption : null);

            Config::set('mail.mailers.smtp.username', Setting::get('mail_username'));
            Config::set('mail.mailers.smtp.password', Setting::get('mail_password'));
            Config::set('mail.from.address', Setting::get('mail_from_address'));
            Config::set('mail.from.name', Setting::get('mail_from_name', config('app.name')));
        } else {
            // If SMTP is selected but not configured, fall back to log
            Config::set('mail.default', 'log');
        }
    }

    public static function testConnection(array $settings): array
    {
        try {
            // Temporarily configure mail with provided settings
            $originalConfig = config('mail');

            $mailer = $settings['mail_mailer'] ?? 'smtp';

            if ($mailer === 'log') {
                return [
                    'success' => true,
                    'message' => 'Log mailer configured successfully (emails will be logged, not sent)',
                ];
            }

            if ($mailer !== 'smtp') {
                return [
                    'success' => false,
                    'message' => 'Invalid mail driver selected',
                ];
            }

            // Configure mail temporarily for testing
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $settings['mail_host'] ?? '');
            Config::set('mail.mailers.smtp.port', $settings['mail_port'] ?? '587');
            Config::set('mail.mailers.smtp.encryption', $settings['mail_encryption'] ?? 'tls');
            Config::set('mail.mailers.smtp.username', $settings['mail_username'] ?? '');
            Config::set('mail.mailers.smtp.password', $settings['mail_password'] ?? '');
            Config::set('mail.from.address', $settings['mail_from_address'] ?? '');
            Config::set('mail.from.name', $settings['mail_from_name'] ?? config('app.name'));

            // Test connection by creating a transport instance
            // Laravel 12 uses Symfony Mailer, so we'll use the DSN approach
            $host = $settings['mail_host'] ?? '';
            $port = $settings['mail_port'] ?? '587';
            $encryption = $settings['mail_encryption'] ?? 'tls';
            $username = $settings['mail_username'] ?? '';
            $password = $settings['mail_password'] ?? '';

            // Build SMTP DSN
            $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
            $dsn = sprintf(
                '%s://%s:%s@%s:%s',
                $scheme,
                urlencode($username),
                urlencode($password),
                $host,
                $port
            );

            // Create transport using Symfony Mailer
            // This will throw an exception if connection fails during creation
            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);

            // For SMTP, we can try to establish connection by checking if it's an SMTP transport
            // and attempting to connect (without sending)
            if ($transport instanceof \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport) {
                // Try to start the connection (this validates credentials)
                $transport->start();
            }

            return [
                'success' => true,
                'message' => 'SMTP connection successful!',
            ];
        } catch (\Exception $e) {
            Log::error('SMTP connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
