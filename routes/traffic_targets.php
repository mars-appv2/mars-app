<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Http\Controllers\TrafficTargetsController;

Route::middleware(['auth'])->prefix('traffic')->name('traffic.')->group(function () {

    Route::prefix('targets')->name('targets.')->group(function () {
        // LIST + CREATE
        Route::get('/',  [TrafficTargetsController::class, 'index'])->name('index');
        Route::post('/', [TrafficTargetsController::class, 'store'])->name('store');

        // ENABLE/DISABLE
        Route::post('{id}/toggle', [TrafficTargetsController::class, 'toggle'])
            ->whereNumber('id')->name('toggle');

        // SHOW: render halaman index yang sama, dengan highlight + panel detail
        Route::get('{id}', function (int $id) {
            $devices  = DB::table('mikrotiks')->select('id','name','host')->orderBy('name')->get();
            $targets  = DB::table('traffic_targets')->orderByDesc('id')->get();
            $selected = DB::table('traffic_targets')->where('id', $id)->first();
            abort_if(!$selected, 404);

            return view('traffic.targets', [
                'devices'    => $devices,
                'targets'    => $targets,
                'selected'   => $selected,
                'selectedId' => $id,
            ]);
        })->whereNumber('id')->name('show');

        // EXPORT + DELETE
        Route::get('{id}/export', [TrafficTargetsController::class, 'export'])
            ->whereNumber('id')->name('export');

        Route::delete('{id}', [TrafficTargetsController::class, 'destroy'])
            ->whereNumber('id')->name('destroy');
    });

    // LEGACY: /traffic/target/view?mikrotik_id=...&target=...
    Route::get('target/view', function (Request $r) {
        $mikrotikId = (int) $r->query('mikrotik_id');
        $key        = trim((string) $r->query('target', ''));
        if (!$mikrotikId || $key === '') abort(404);

        if (!Schema::hasColumn('traffic_targets','target_key')) abort(404);

        $row = DB::table('traffic_targets')
            ->where('mikrotik_id', $mikrotikId)
            ->where('target_key', $key)
            ->orderByDesc('id')
            ->first();

        abort_if(!$row, 404);

        // Redirect ke halaman show yang NON-redirect loop
        return redirect()->route('traffic.targets.show', ['id' => $row->id]);
    })->name('targets.legacy_view');

});
