<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\StorageSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __invoke(Backup $backup): BinaryFileResponse|RedirectResponse
    {
        if ($backup->storage_driver === 's3') {
            StorageSettingsService::configureStorage();

            if (!Storage::disk('s3')->exists($backup->file_path)) {
                abort(404, 'Backup file not found in S3');
            }

            // Redirect directly to S3 temporary URL - PHP does nothing, client downloads from S3
            return redirect()->away(
                Storage::disk('s3')->temporaryUrl($backup->file_path, now()->addMinutes(5), [
                    'ResponseContentDisposition' => 'attachment; filename="' . $backup->file_name . '"',
                ])
            );
        }

        $filePath = storage_path('app/' . $backup->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'Backup file not found');
        }

        // Direct file response - let web server handle it via X-Sendfile/X-Accel-Redirect
        return response()->file($filePath)->deleteFileAfterSend(false);
    }
}
