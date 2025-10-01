<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Http\Controllers\Traffic\GraphsController;

/* Semua halaman di bawah login */
Route::middleware(['auth'])->prefix('traffic/graphs')->name('traffic.graphs.')->group(function () {

    // Landing & list pages
    Route::get('/',           [GraphsController::class,'index'])->name('index');
    Route::get('/interfaces', [GraphsController::class,'interfaces'])->name('interfaces');
    Route::get('/pppoe',      [GraphsController::class,'pppoe'])->name('pppoe');
    Route::get('/ip',         [GraphsController::class,'ip'])->name('ip');
    Route::get('/content',    [GraphsController::class,'content'])->name('content');

    // Detail target (traffic_targets)
    Route::get('/target/{id}', [GraphsController::class,'show'])->whereNumber('id')->name('show');

    // Detail content (traffic_content_map)
    Route::get('/content/show/{id}', [GraphsController::class,'contentDetail'])
        ->whereNumber('id')->name('content.show');

    // PNG server untuk semua grup
    Route::get('/png/{group}/{key}/{period}', [GraphsController::class,'png'])
        ->where([
            'group'  => 'interfaces|pppoe|ip|content',
            'period' => 'day|week|month|year',
        ])->name('png');

    // Aksi: simpan snapshot, poll/ping 1 target
    Route::post('/save',        [GraphsController::class,'saveSnapshot'])->name('save');
    Route::post('/poll/{id}',   [GraphsController::class,'pollOne'])->whereNumber('id')->name('poll'); // untuk traffic:poll
    Route::post('/ping/{id}',   [GraphsController::class,'pingOne'])->whereNumber('id')->name('ping'); // untuk traffic:ping
});

/* Tambah mapping konten (Google/Meta/TikTok dsb) */
Route::post('/traffic/content/targets', function (Request $r) {
    DB::table('traffic_content_map')->insert([
        'name'       => trim((string)$r->input('name')),
        'cidr'       => trim((string)$r->input('cidr')),
        'enabled'    => $r->boolean('enabled', true) ? 1 : 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    return back()->with('ok','Konten ditambahkan');
})->middleware('auth')->name('traffic.content.targets.store');
