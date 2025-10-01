<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MonitorGroupController;

Route::middleware(['auth'])->group(function(){
    Route::get('mikrotik/{mikrotik}/groups',        [MonitorGroupController::class,'index'])->name('mikrotik.groups.index');
    Route::get('mikrotik/{mikrotik}/groups/{group}',[MonitorGroupController::class,'show'])->name('mikrotik.groups.show');
    Route::post('mikrotik/{mikrotik}/groups',       [MonitorGroupController::class,'store'])->name('mikrotik.groups.store');
    Route::delete('mikrotik/{mikrotik}/groups/{group}',[MonitorGroupController::class,'destroy'])->name('mikrotik.groups.destroy');
});
