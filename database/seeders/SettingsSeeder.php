<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMailSettings();
        $this->seedS3Settings();
    }

    private function seedMailSettings(): void
    {
        $mailMappings = [
            'mail_mailer'       => env('MAIL_MAILER'),
            'mail_host'         => env('MAIL_HOST'),
            'mail_port'         => (string) env('MAIL_PORT'),
            'mail_encryption'   => env('MAIL_ENCRYPTION', 'tls'),
            'mail_username'     => env('MAIL_USERNAME'),
            'mail_password'     => env('MAIL_PASSWORD'),
            'mail_from_address' => env('MAIL_FROM_ADDRESS'),
            'mail_from_name'    => env('MAIL_FROM_NAME'),
        ];

        $hasSmtp = !empty($mailMappings['mail_host'])
            && !empty($mailMappings['mail_username'])
            && !empty($mailMappings['mail_password']);

        if (!$hasSmtp && ($mailMappings['mail_mailer'] ?? 'log') !== 'log') {
            $this->command->warn('Mail SMTP credentials incomplete in .env — skipping mail settings.');
            return;
        }

        foreach ($mailMappings as $key => $value) {
            if ($value !== null && $value !== '') {
                Setting::set($key, $value);
            }
        }

        $this->command->info('Mail settings seeded from .env.');
    }

    private function seedS3Settings(): void
    {
        $awsKey    = env('AWS_ACCESS_KEY_ID');
        $awsSecret = env('AWS_SECRET_ACCESS_KEY');
        $awsBucket = env('AWS_BUCKET');

        if (empty($awsKey) || empty($awsSecret) || empty($awsBucket)) {
            $this->command->warn('AWS S3 credentials incomplete in .env — skipping S3 settings.');
            return;
        }

        Setting::set('storage_driver', 's3');
        Setting::set('s3_key', $awsKey);
        Setting::set('s3_secret', $awsSecret);
        Setting::set('s3_bucket', $awsBucket);

        $region = env('AWS_DEFAULT_REGION');
        if (!empty($region)) {
            Setting::set('s3_region', $region);
        }

        $endpoint = env('AWS_ENDPOINT');
        if (!empty($endpoint)) {
            Setting::set('s3_endpoint', $endpoint);
        }

        $pathStyle = env('AWS_USE_PATH_STYLE_ENDPOINT', 'false');
        Setting::set('s3_path_style', $pathStyle);

        $this->command->info('S3 settings seeded from .env.');
    }
}
