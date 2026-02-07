<?php

namespace App\Filament\Traits;

use App\Filament\Support\SettingsChecker;
use Filament\Notifications\Notification;

trait RequiresSettings
{
    /**
     * Check if all required settings are configured.
     */
    protected function areSettingsConfigured(): bool
    {
        return SettingsChecker::isConfigured();
    }

    /**
     * Check if SMTP mail settings are configured.
     */
    protected function isMailConfigured(): bool
    {
        return SettingsChecker::isMailConfigured();
    }

    /**
     * Check if S3 storage settings are configured.
     */
    protected function isStorageConfigured(): bool
    {
        return SettingsChecker::isStorageConfigured();
    }

    /**
     * Get the list of missing settings.
     *
     * @return array<string>
     */
    protected function getMissingSettings(): array
    {
        return SettingsChecker::getMissing();
    }

    /**
     * Get a formatted message about missing settings.
     */
    protected function getMissingSettingsMessage(): string
    {
        return SettingsChecker::getMissingMessage();
    }

    /**
     * Show a notification about missing settings and redirect to settings page.
     */
    protected function guardSettingsOrRedirect(): bool
    {
        if ($this->areSettingsConfigured()) {
            return true;
        }

        Notification::make()
            ->title('Settings Required')
            ->body($this->getMissingSettingsMessage())
            ->warning()
            ->persistent()
            ->actions([
                \Filament\Actions\Action::make('configure')
                    ->label('Go to Settings')
                    ->url(\App\Filament\Pages\Settings\SmtpSettings::getUrl())
                    ->button(),
            ])
            ->send();

        $this->redirect(\App\Filament\Pages\Settings\SmtpSettings::getUrl());

        return false;
    }
}
