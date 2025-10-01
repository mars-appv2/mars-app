<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['web','auth'])->prefix('traffic')->name('traffic.')->group(function(){
  Route::get('/', [\App\Http\Controllers\Traffic\TrafficController::class, 'index'])->name('index');
  Route::get('/interfaces', [\App\Http\Controllers\Traffic\TrafficController::class, 'interfaces'])->name('interfaces');
  Route::get('/pppoe', [\App\Http\Controllers\Traffic\TrafficController::class, 'pppoe'])->name('pppoe');
  Route::get('/content', [\App\Http\Controllers\Traffic\TrafficController::class, 'content'])->name('content');
  Route::post('/save', [\App\Http\Controllers\Traffic\TrafficController::class, 'savePng'])->name('save');
  Route::get('/img/{path}', [\App\Http\Controllers\Traffic\TrafficController::class, 'image'])->where('path','.*')->name('img');
});