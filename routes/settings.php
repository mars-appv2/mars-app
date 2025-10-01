<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingsController;

Route::middleware(['auth','role:admin'])->group(function () {
    Route::get ('/settings/roles', [SettingsController::class, 'roles'])->name('settings.roles');
    Route::post('/settings/roles', [SettingsController::class, 'rolesSave'])->name('settings.roles.save');
});
