<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\SettingsController;

// Mikrotik
Route::middleware(['auth','permission:manage mikrotik'])->group(function(){
  Route::get('/mikrotik',[MikrotikController::class,'index'])->name('mikrotik.index');
  Route::post('/mikrotik',[MikrotikController::class,'store'])->name('mikrotik.store');
  Route::post('/mikrotik/{mikrotik}/delete',[MikrotikController::class,'delete'])->name('mikrotik.delete');
  Route::get('/mikrotik/{mikrotik}/dashboard',[MikrotikController::class,'dashboard'])->name('mikrotik.dashboard');
  Route::get('/mikrotik/{mikrotik}/monitor',[MikrotikController::class,'monitor'])->name('mikrotik.monitor');
  Route::post('/mikrotik/{mikrotik}/vlan',[MikrotikController::class,'vlanCreate'])->name('mikrotik.vlan');
  Route::post('/mikrotik/{mikrotik}/bridge',[MikrotikController::class,'bridgeCreate'])->name('mikrotik.bridge');
  Route::get('/mikrotik/{mikrotik}/pppoe',[MikrotikController::class,'pppIndex'])->name('mikrotik.pppoe');
  Route::post('/mikrotik/{mikrotik}/pppoe/add',[MikrotikController::class,'pppAdd'])->name('mikrotik.pppoe.add');
  Route::post('/mikrotik/{mikrotik}/pppoe/edit',[MikrotikController::class,'pppEdit'])->name('mikrotik.pppoe.edit');
  Route::post('/mikrotik/{mikrotik}/pppoe/delete',[MikrotikController::class,'pppoeDelete'])->name('mikrotik.pppoe.delete');
  Route::get('/mikrotik/{mikrotik}/ip-static',[MikrotikController::class,'ipStatic'])->name('mikrotik.ipstatic');
  Route::post('/mikrotik/{mikrotik}/ip-static/add',[MikrotikController::class,'ipStaticAdd'])->name('mikrotik.ipstatic.add');
  Route::post('/mikrotik/{mikrotik}/ip-static/remove',[MikrotikController::class,'ipStaticRemove'])->name('mikrotik.ipstatic.remove');
  Route::post('/mikrotik/{mikrotik}/ip-static/record',[MikrotikController::class,'ipStaticRecord'])->name('mikrotik.ipstatic.record');
  Route::post('/mikrotik/{mikrotik}/monitor/interface',[MikrotikController::class,'addInterfaceTarget'])->name('mikrotik.monitor.addInterface');
});

// Billing
Route::middleware(['auth','permission:manage billing'])->group(function(){
  Route::get('/billing',[BillingController::class,'index'])->name('billing.index');
  Route::get('/billing/create',[BillingController::class,'create'])->name('billing.create');
  Route::post('/billing',[BillingController::class,'store'])->name('billing.store');
  Route::post('/billing/{invoice}/paid',[BillingController::class,'markPaid'])->name('billing.paid');
  Route::delete('/billing/{invoice}',[BillingController::class,'destroy'])->name('billing.delete');
  Route::get('/billing/{invoice}/pdf/{size}',[BillingController::class,'pdf'])->name('billing.pdf');
  Route::get('/billing/cashflow',[BillingController::class,'cashflow'])->name('billing.cashflow');
  Route::post('/billing/cashflow',[BillingController::class,'cashflowStore']);
});

// Settings
Route::middleware(['auth','permission:manage settings'])->group(function(){
  Route::get('/settings/telegram',[SettingsController::class,'telegram'])->name('settings.telegram');
  Route::post('/settings/telegram',[SettingsController::class,'telegramSave']);
  Route::get('/settings/whatsapp',[SettingsController::class,'whatsapp'])->name('settings.whatsapp');
  Route::post('/settings/whatsapp',[SettingsController::class,'whatsappSave']);
  Route::get('/settings/payment',[SettingsController::class,'payment'])->name('settings.payment');
  Route::post('/settings/payment',[SettingsController::class,'paymentSave']);
  Route::get('/settings/roles',[SettingsController::class,'roles'])->name('settings.roles');
  Route::post('/settings/roles',[SettingsController::class,'rolesSave']);
});

// Placeholder Radius
Route::middleware(['auth','permission:manage radius'])->group(function(){
  Route::get('/radius', fn()=>view('dashboard'))->name('radius.index');
});
