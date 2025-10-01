<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentsUiController;

Route::middleware(['auth','permission:manage billing'])
    ->prefix('payments')->name('payments.')
    ->group(function () {

    Route::get ('/',         [PaymentsUiController::class, 'index'])->name('index');

    // Manual
    Route::get  ('/manual',       [PaymentsUiController::class, 'manual'])->name('manual');
    Route::post ('/manual/save',  [PaymentsUiController::class, 'saveManual'])->name('manual.save');

    // Gateway (cred/config)
    Route::get  ('/gateway',      [PaymentsUiController::class, 'gateway'])->name('gateway');
    Route::post ('/gateway/save', [PaymentsUiController::class, 'saveGateway'])->name('gateway.save');
});
