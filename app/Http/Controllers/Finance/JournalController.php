<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade as PDF;



class JournalController extends Controller
{
    public function kas()
    {
        $cashAccounts = Account::where('is_cash', true)->orderBy('code')->get();
        $allAccounts  = Account::orderBy('code')->get();

        $recent = JournalEntry::with(['lines.account'])
            ->orderBy('date','desc')->orderBy('id','desc')
            ->limit(25)->get();

        return view('finance.journal.kas', compact('cashAccounts','allAccounts','recent'));
    }

    public function storeKas(Request $request)
    {
        $data = $request->validate([
            'date'               => 'required|date',
            'type'               => 'required|in:in,out',
            'cash_account_id'    => 'required|exists:accounts,id',
            'counter_account_id' => 'required|exists:accounts,id',
            'amount'             => 'required|numeric|min:0.01',
            'description'        => 'nullable|string',
            'ref'                => 'nullable|string|max:50',
        ]);

        // Pastikan akun kas memang bertanda is_cash
        $cash = Account::findOrFail($data['cash_account_id']);
        if (!$cash->is_cash) {
            return back()->withErrors(['cash_account_id' => 'Akun dipilih bukan akun kas/bank.']);
        }

        DB::transaction(function() use ($data) {
            $entry = JournalEntry::create([
                'date'        => $data['date'],
                'ref'         => $data['ref'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by'  => Auth::id(),
                'posted_at'   => now(),
            ]);

            $counterId = (int) $data['counter_account_id'];
            $amount    = round((float) $data['amount'], 2);

            if ($data['type'] === 'in') {
                // Kas Masuk: Debit Kas, Kredit Lawan
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => (int) $data['cash_account_id'],
                    'debit'            => $amount,
                    'credit'           => 0,
                    'memo'             => 'Kas Masuk',
                ]);
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $counterId,
                    'debit'            => 0,
                    'credit'           => $amount,
                    'memo'             => 'Kas Masuk (lawan)',
                ]);
            } else {
                // Kas Keluar: Debit Lawan, Kredit Kas
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $counterId,
                    'debit'            => $amount,
                    'credit'           => 0,
                    'memo'             => 'Kas Keluar',
                ]);
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => (int) $data['cash_account_id'],
                    'debit'            => 0,
                    'credit'           => $amount,
                    'memo'             => 'Kas Keluar (lawan)',
                ]);
            }
        });

        return back()->with('ok','Transaksi kas tersimpan.');
    }

    // --- Jurnal Umum ---
    public function jurnalUmum() {
        $accounts = \App\Models\Account::orderBy('code')->get();
        return view('finance.journal.jurnal_umum', compact('accounts'));
    }

    public function storeJurnalUmum(\Illuminate\Http\Request $r) {
        $d = $r->validate([
            'date' => 'required|date',
            'ref'  => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit'  => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.memo'   => 'nullable|string|max:255',
        ]);
        $D=0; $C=0; $clean=[];
        foreach ($d['lines'] as $i=>$l) {
            $dd = round((float)($l['debit'] ?? 0), 2);
            $cc = round((float)($l['credit']?? 0), 2);
            if ($dd>0 && $cc>0) return back()->withErrors('Baris '.($i+1).': tidak boleh debit & kredit sekaligus.')->withInput();
            if ($dd==0 && $cc==0) continue;
            $clean[] = ['account_id'=>(int)$l['account_id'],'debit'=>$dd,'credit'=>$cc,'memo'=>$l['memo']??null];
            $D += $dd; $C += $cc;
        }
        if (count($clean)<2) return back()->withErrors('Minimal 2 baris (debit & kredit).')->withInput();
        if (abs($D-$C)>0.009) return back()->withErrors('Total debit harus sama dengan total kredit.')->withInput();

        \Illuminate\Support\Facades\DB::transaction(function() use ($d,$clean){
            $e = \App\Models\JournalEntry::create([
                'date'=>$d['date'],'ref'=>$d['ref']??null,'description'=>$d['description']??null,
                'created_by'=>\Illuminate\Support\Facades\Auth::id(),'posted_at'=>now(),
            ]);
            foreach ($clean as $row) {
                \App\Models\JournalLine::create(['journal_entry_id'=>$e->id]+$row);
            }
        });
        return back()->with('ok','Jurnal umum tersimpan.');
    }

    // ------- EXPORT: JURNAL UMUM (CSV) -------
    public function exportJournalCsv(Request $r): StreamedResponse
    {
        $start=$r->input('start'); $end=$r->input('end');
        $entries = JournalEntry::with(['lines.account'])
            ->when($start, fn($q)=>$q->where('date','>=',$start))
            ->when($end,   fn($q)=>$q->where('date','<=',$end))
            ->orderBy('date')->orderBy('id')->get();

        $filename = 'general_journal_'.date('Ymd_His').'.csv';
        return new StreamedResponse(function() use ($entries){
            $out=fopen('php://output','w');
            fputcsv($out, ['Tanggal','Ref','Deskripsi','Akun','Debit','Kredit','Memo']);
            foreach($entries as $e){
                foreach($e->lines as $l){
                    $akun = $l->account ? ($l->account->code.' '.$l->account->name) : '';
                    fputcsv($out, [
                        $e->date->format('Y-m-d'),
                        $e->ref, $e->description,
                        $akun,
                        number_format((float)$l->debit, 2, '.', ''),
                        number_format((float)$l->credit, 2, '.', ''),
                        $l->memo
                    ]);
                }
                // baris pemisah per entri (opsional)
                // fputcsv($out, []);
            }
            fclose($out);
        },200,['Content-Type'=>'text/csv','Content-Disposition'=>"attachment; filename=\"$filename\""]);
    }

    // ------- EXPORT: JURNAL UMUM (PDF) -------
    public function exportJournalPdf(Request $r)
    {
        $start=$r->input('start'); $end=$r->input('end');
        $entries = JournalEntry::with(['lines.account'])
            ->when($start, fn($q)=>$q->where('date','>=',$start))
            ->when($end,   fn($q)=>$q->where('date','<=',$end))
            ->orderBy('date')->orderBy('id')->get();

        $pdf = PDF::loadView('finance.reports.pdf.journal', compact('entries','start','end'))
              ->setPaper('a4','portrait');
        return $pdf->download('general_journal_'.date('Ymd_His').'.pdf');
    } 
    
    
}   




