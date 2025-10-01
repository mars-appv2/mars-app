<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MikrotikBackupController;

Route::middleware(['auth','permission:manage mikrotik'])->group(function () {
    // daftar semua device + latest backup
    Route::get('/backups', [MikrotikBackupController::class, 'indexAll'])->name('backups.index');

    // per device
    Route::prefix('mikrotik/{mikrotik}')->group(function () {
        Route::get   ('/backups',                      [MikrotikBackupController::class, 'index'])->name('mikrotik.backups');
        Route::post  ('/backups/run',                  [MikrotikBackupController::class, 'run'])->name('mikrotik.backups.run');
        Route::get   ('/backups/{backup}/download',    [MikrotikBackupController::class, 'download'])->name('mikrotik.backups.download');
        Route::delete('/backups/{backup}',             [MikrotikBackupController::class, 'delete'])->name('mikrotik.backups.delete');
        Route::post  ('/backups/{backup}/restore',     [MikrotikBackupController::class, 'restore'])->name('mikrotik.backups.restore');
    });
});
