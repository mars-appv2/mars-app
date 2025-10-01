<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\JournalController;
use App\Http\Controllers\Finance\ReportController;

Route::middleware(['web','auth'])->prefix('finance')->name('finance.')->group(function(){
    // Nomor Akun (Chart of Accounts)
    Route::get('/accounts', [AccountController::class,'index'])->name('accounts');
    Route::post('/accounts', [AccountController::class,'store'])->name('accounts.store');
    Route::post('/accounts/{account}', [AccountController::class,'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class,'destroy'])->name('accounts.delete');

    // Kas Masuk/Keluar
    Route::get('/kas', [JournalController::class,'kas'])->name('kas');
    Route::post('/kas', [JournalController::class,'storeKas'])->name('kas.store');

    // Reports
    Route::get('/ledger', [ReportController::class,'ledger'])->name('ledger'); // Buku Besar
    Route::get('/cash-ledger', [ReportController::class,'cashLedger'])->name('cash'); // Lajur Kas
    Route::get('/trial-balance', [ReportController::class,'trialBalance'])->name('trial'); // Neraca Percobaan
    Route::get('/balance-sheet', [ReportController::class,'balanceSheet'])->name('balance'); // Neraca

    Route::get('/jurnal-umum',  [\App\Http\Controllers\Finance\JournalController::class, 'jurnalUmum'])->name('jurnal');
    Route::post('/jurnal-umum', [\App\Http\Controllers\Finance\JournalController::class, 'storeJurnalUmum'])->name('jurnal.store');

    // EXPORT CSV
    Route::get('/ledger/export/csv',        [ReportController::class,'exportLedgerCsv'])->name('ledger.export.csv');
    Route::get('/cash-ledger/export/csv',   [ReportController::class,'exportCashLedgerCsv'])->name('cash.export.csv');
    Route::get('/trial-balance/export/csv', [ReportController::class,'exportTrialBalanceCsv'])->name('trial.export.csv');
    Route::get('/balance-sheet/export/csv', [ReportController::class,'exportBalanceSheetCsv'])->name('balance.export.csv');

    // EXPORT PDF (pakai barryvdh/laravel-dompdf — paket kamu sudah terpasang)
    Route::get('/trial-balance/export/pdf', [ReportController::class,'exportTrialBalancePdf'])->name('trial.export.pdf');
    Route::get('/balance-sheet/export/pdf', [ReportController::class,'exportBalanceSheetPdf'])->name('balance.export.pdf');

    
    // EXPORT CSV/PDF (Jurnal Umum)
    Route::get('/journal/export/csv', [JournalController::class,'exportJournalCsv'])->name('journal.export.csv');
    Route::get('/journal/export/pdf', [JournalController::class,'exportJournalPdf'])->name('journal.export.pdf');

});
