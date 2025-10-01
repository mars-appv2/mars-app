<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Mikrotik;
use App\Models\MikrotikBackup;
use App\Services\MikrotikBackupService;

class MikrotikBackupController extends Controller
{
    public function indexAll()
    {
        $this->middleware('auth');
        $devices = Mikrotik::forUser(auth()->user())
            ->orderBy('name')->get(['id','name','host']);

        $latest = [];
        foreach ($devices as $d) {
            $row = MikrotikBackup::where('mikrotik_id',$d->id)->orderBy('id','desc')->first();
            $latest[$d->id] = $row;
        }

        return view('mikrotik.backups_index', compact('devices','latest'));
    }

    public function index(Mikrotik $mikrotik)
    {
        $this->authorize('view', $mikrotik);
        $rows = MikrotikBackup::where('mikrotik_id',$mikrotik->id)
            ->orderBy('id','desc')->limit(500)->get();

        return view('mikrotik.backups', compact('mikrotik','rows'));
    }

    public function run(Mikrotik $mikrotik, Request $r, MikrotikBackupService $svc)
    {
        $this->authorize('update', $mikrotik);
        $modes = $r->has('modes') ? array_values(array_filter((array)$r->input('modes'))) : null;
        $svc->runForDevice($mikrotik, $modes);
        return back()->with('ok','Backup dibuat.');
    }

    public function download(Mikrotik $mikrotik, MikrotikBackup $backup)
    {
        $this->authorize('view', $mikrotik);
        abort_unless($backup->mikrotik_id === $mikrotik->id, 404);
        abort_unless(Storage::exists($backup->filename), 404);

        $basename = basename($backup->filename);
        return Storage::download($backup->filename, $basename);
    }

    public function delete(Mikrotik $mikrotik, MikrotikBackup $backup)
    {
        $this->authorize('update', $mikrotik);
        abort_unless($backup->mikrotik_id === $mikrotik->id, 404);

        if (Storage::exists($backup->filename)) Storage::delete($backup->filename);
        $backup->delete();

        return back()->with('ok','Backup dihapus.');
    }

    public function restore(Mikrotik $mikrotik, MikrotikBackup $backup, Request $r, MikrotikBackupService $svc)
    {
        $this->authorize('update', $mikrotik);
        abort_unless($backup->mikrotik_id === $mikrotik->id, 404);

        try {
            $replace = $r->has('replace');
            $res = $svc->restoreFromJson($mikrotik, $backup, $replace);
            $msg = 'Restore selesai: '
                .' processed='.$res['processed']
                .', added='.$res['added']
                .', updated='.$res['updated']
                .($replace ? ', deleted='.$res['deleted'] : '')
                .', failed='.$res['failed'];
            return back()->with('ok', $msg);
        } catch (\Throwable $e) {
            return back()->with('err', 'Restore gagal: '.$e->getMessage());
        }
    }
}
