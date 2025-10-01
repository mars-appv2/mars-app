<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillingUiController;

Route::middleware(['auth','permission:manage billing'])
    ->prefix('billing')->name('billing.')
    ->group(function () {

    /* ---------- PLANS ---------- */
    Route::get  ('/plans',        [BillingUiController::class, 'plans'])->name('plans');
    Route::post ('/plans/import', [BillingUiController::class, 'importPlans'])->name('plans.import'); // letakkan di atas {id}
    Route::post ('/plans/store',  [BillingUiController::class, 'planStore'])->name('plans.store');
    Route::post ('/plans/{id}',   [BillingUiController::class, 'planUpdate'])->whereNumber('id')->name('plans.update');
    Route::delete('/plans/{id}',  [BillingUiController::class, 'planDelete'])->whereNumber('id')->name('plans.delete');

    /* ---------- SUBSCRIPTIONS ---------- */
    Route::get ('/subs',                 [BillingUiController::class, 'subs'])->name('subs');
    Route::post('/subs/bulk-delete',     [BillingUiController::class, 'subsBulkDelete'])->name('subs.bulkDelete');

    /* ---------- INVOICES ---------- */
    Route::get ('/invoices',             [BillingUiController::class, 'invoices'])->name('invoices');
    Route::post('/invoices/bulk-delete', [BillingUiController::class, 'invoicesBulkDelete'])->name('invoices.bulkDelete');
    Route::get ('/invoices/{id}/print',  [BillingUiController::class,'invoicePrint'])->whereNumber('id')->name('invoices.print');
    Route::get ('/invoices/{id}',        [BillingUiController::class,'invoiceShow'])->whereNumber('id')->name('invoices.show');

    /* ---------- TOOLS ---------- */
    Route::post('/tools/sync',           [BillingUiController::class, 'toolsSync'])->name('tools.sync');
    Route::post('/tools/generate',       [BillingUiController::class, 'toolsGenerate'])->name('tools.generate');
    Route::post('/tools/enforce',        [BillingUiController::class, 'toolsEnforce'])->name('tools.enforce');

    /* ---------- TEMPLATE (INVOICE) ---------- */
    Route::get ('/template',             [BillingUiController::class, 'templateEdit'])->name('template.edit');
    Route::post('/template',             [BillingUiController::class, 'templateSave'])->name('template.save');

    /* ---------- PAYMENTS (Manual) ---------- */
    Route::get ('/payments',             [BillingUiController::class, 'payments'])->name('payments');
    Route::post('/payments/mark-paid',   [BillingUiController::class, 'paymentsMarkPaid'])->name('payments.markPaid');
    Route::post('/payments/bulk-paid',   [BillingUiController::class, 'paymentsBulkPaid'])->name('payments.bulkPaid');
});

