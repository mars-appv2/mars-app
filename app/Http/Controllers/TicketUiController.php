<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketUiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** List tiket dengan filter sederhana. */
    public function index(Request $r)
    {
        $qStatus = $r->query('status', 'open');      // open|closed|all
        $qType   = $r->query('type', 'all');         // psb|complain|all
        $qSearch = trim((string)$r->query('q', ''));

        $rows = DB::table('tickets')
            ->when($qStatus !== 'all', function($q) use ($qStatus) {
                $q->where('status', $qStatus);
            })
            ->when($qType !== 'all', function($q) use ($qType) {
                $q->where('type', $qType);
            })
            ->when($qSearch !== '', function($q) use ($qSearch) {
                $q->where(function($w) use ($qSearch) {
                    $w->where('code','like',"%$qSearch%")
                      ->orWhere('username','like',"%$qSearch%")
                      ->orWhere('customer_name','like',"%$qSearch%")
                      ->orWhere('description','like',"%$qSearch%");
                });
            })
            ->orderByRaw("CASE WHEN status='open' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Hitung jumlah komplain open untuk banner alarm
        $alarmCount = (int) DB::table('tickets')
            ->where('type','complain')
            ->where('status','open')
            ->count();

        return view('tickets.index', [
            'rows'       => $rows,
            'qStatus'    => $qStatus,
            'qType'      => $qType,
            'qSearch'    => $qSearch,
            'alarmCount' => $alarmCount,
        ]);
    }

    /** Close tiket via UI (tombol). */
    public function close(Request $r, $ticketId)
    {
        $note = trim((string) $r->input('note','')); // catatan penutupan (opsional)
        $uid  = auth()->id();

        // Ambil tiket
        $t = DB::table('tickets')->where('id', $ticketId)->first();
        if (!$t) {
            return redirect()->route('tickets.index')->with('err', 'Tiket tidak ditemukan.');
        }
        if ($t->status === 'closed') {
            return redirect()->route('tickets.index')->with('ok', 'Tiket sudah closed.');
        }

        // Update kolom yang tersedia
        $data = [
            'status'     => 'closed',
            'updated_at' => now(),
        ];

        // isi kolom jika ada (opsional)
        if (Schema::hasColumn('tickets', 'closed_at')) $data['closed_at'] = now();
        if (Schema::hasColumn('tickets', 'closed_by')) $data['closed_by'] = $uid;
        if ($note !== '') {
            if (Schema::hasColumn('tickets', 'resolution')) {
                $data['resolution'] = $note;
            } else {
                // fallback: tambahkan catatan ke description
                $data['description'] = trim(($t->description ?? '')."\n[Closed note] ".$note);
            }
        }

        DB::table('tickets')->where('id',$ticketId)->update($data);

        return redirect()->route('tickets.index')->with('ok', 'Tiket berhasil ditutup.');
    }
}
