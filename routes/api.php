<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RadiusClientController;
use App\Http\Controllers\WaBotController;

// Endpoint data Radius (pakai middleware kunci seperti sebelumnya)
Route::middleware('radius.key')->get('/radius/clients', [RadiusClientController::class, 'index']);

// Webhook WA: HANYA SATU, ke WaBotController@webhook
Route::post('/wa/webhook', [WaBotController::class, 'webhook'])->name('wa.webhook');

Route::post('/telegram/{secret}', 'Api\TelegramWebhookController@handle')
    ->where('secret', '[A-Za-z0-9\-]+');
