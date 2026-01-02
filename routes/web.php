<?php

use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin', 301);

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/backups/{backup}/download', BackupController::class)
        ->name('backups.download');
});
