<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade as PDF;

class ReportController extends Controller
{
    /* ===========================
     * BUKU BESAR
     * =========================== */
    public function ledger(Request $request)
    {
        $accountId = (int) $request->input('account_id');
        $start = $request->input('start');
        $end   = $request->input('end');

        $accounts = Account::orderBy('code')->get();
        $account  = $accountId ? Account::find($accountId) : null;

        $lines    = collect();
        $opening  = 0.0;

        if ($account) {
            // saldo awal s/d < $start
            $qOpen = JournalLine::where('account_id', $account->id);
            if ($start) {
                $qOpen->whereHas('entry', function($q) use ($start) {
                    $q->where('date','<',$start);
                });
            }
            $sumDebit  = (float) $qOpen->sum('debit');
            $sumCredit = (float) $qOpen->sum('credit');
            $opening   = self::signedBalance($account->type, $sumDebit, $sumCredit);

            // AMAN: urut di DB via JOIN (tanpa "order by journal_entries as je")
            $lines = JournalLine::select('journal_lines.*')
                ->with('entry')
                ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $account->id)
                ->when($start, fn($q) => $q->where('je.date', '>=', $start))
                ->when($end,   fn($q) => $q->where('je.date', '<=', $end))
                ->orderBy('je.date')
                ->orderBy('je.id')
                ->get();
        }

        return view('finance.reports.ledger', compact('accounts','account','lines','opening','start','end'));
    }

    /* ===========================
     * LAJUR KAS
     * =========================== */
    public function cashLedger(Request $request)
    {
        $cashAccounts = Account::where('is_cash', true)->orderBy('code')->get();
        $accountId = (int) $request->input('account_id');
        $start = $request->input('start');
        $end   = $request->input('end');

        $account = $accountId ? Account::find($accountId) : $cashAccounts->first();

        $lines    = collect();
        $opening  = 0.0;

        if ($account) {
            // saldo awal s/d < $start
            $qOpen = JournalLine::where('account_id', $account->id);
            if ($start) {
                $qOpen->whereHas('entry', function($q) use ($start) {
                    $q->where('date','<',$start);
                });
            }
            $sumDebit  = (float) $qOpen->sum('debit');
            $sumCredit = (float) $qOpen->sum('credit');
            $opening   = self::signedBalance($account->type, $sumDebit, $sumCredit);

            // urut via JOIN supaya konsisten
            $lines = JournalLine::select('journal_lines.*')
                ->with('entry')
                ->join('journal_entries as je','je.id','=','journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $account->id)
                ->when($start, fn($q) => $q->where('je.date','>=',$start))
                ->when($end,   fn($q) => $q->where('je.date','<=',$end))
                ->orderBy('je.date')
                ->orderBy('je.id')
                ->get();
        }

        return view('finance.reports.cash_ledger', compact('cashAccounts','account','lines','opening','start','end'));
    }

    /* ===========================
     * NERACA PERCOBAAN
     * (punya kamu — dipertahankan)
     * =========================== */
    public function trialBalance(Request $request)
    {
        $start = $request->input('start');
        $end   = $request->input('end');

        $accounts = Account::where('is_active',true)->orderBy('code')->get();

        $rows = [];
        $totalDebit = 0; $totalCredit = 0;

        foreach ($accounts as $a) {
            $q = JournalLine::where('account_id',$a->id);
            if ($start) $q->whereHas('entry', function($qq) use ($start){ $qq->where('date','>=',$start); });
            if ($end)   $q->whereHas('entry', function($qq) use ($end){   $qq->where('date','<=',$end);   });
            $debit  = (float) $q->sum('debit');
            $credit = (float) $q->sum('credit');

            $balance = self::signedBalance($a->type, $debit, $credit);
            $debitCol = 0; $creditCol = 0;
            if (in_array($a->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE])) {
                $debitCol  = $balance >= 0 ? $balance : 0;
                $creditCol = $balance <  0 ? abs($balance) : 0;
            } else {
                $creditCol = $balance >= 0 ? $balance : 0;
                $debitCol  = $balance <  0 ? abs($balance) : 0;
            }

            $rows[] = compact('a','debitCol','creditCol');
            $totalDebit  += $debitCol;
            $totalCredit += $creditCol;
        }

        return view('finance.reports.trial_balance', compact('rows','totalDebit','totalCredit','start','end'));
    }

    /* ===========================
     * NERACA
     * (punya kamu — dipertahankan)
     * =========================== */
    public function balanceSheet(Request $request)
    {
        $asOf = $request->input('as_of');

        $grouped = [
            'assets'     => [],
            'liabilities'=> [],
            'equity'     => [],
        ];
        $sum = [ 'assets'=>0.0, 'liabilities'=>0.0, 'equity'=>0.0 ];

        $accounts = Account::where('is_active',true)->orderBy('code')->get();

        $revenueTotal = 0.0; $expenseTotal = 0.0;

        foreach ($accounts as $a) {
            $q = JournalLine::where('account_id',$a->id);
            if ($asOf) $q->whereHas('entry', function($qq) use ($asOf){ $qq->where('date','<=',$asOf); });
            $debit  = (float) $q->sum('debit');
            $credit = (float) $q->sum('credit');
            $balance = self::signedBalance($a->type, $debit, $credit);

            switch ($a->type) {
                case Account::TYPE_ASSET:
                    $grouped['assets'][] = [$a, $balance];
                    $sum['assets'] += $balance; break;
                case Account::TYPE_LIAB:
                    $grouped['liabilities'][] = [$a, $balance];
                    $sum['liabilities'] += $balance; break;
                case Account::TYPE_EQUITY:
                    $grouped['equity'][] = [$a, $balance];
                    $sum['equity'] += $balance; break;
                case Account::TYPE_REVENUE:
                    $revenueTotal += $balance; break;
                case Account::TYPE_EXPENSE:
                    $expenseTotal += $balance; break;
            }
        }

        $netIncome = $revenueTotal - $expenseTotal;
        $sum['equity'] += $netIncome;

        return view('finance.reports.balance_sheet', compact('grouped','sum','netIncome','asOf'));
    }

    /* ===========================
     * UTIL
     * =========================== */
    public static function signedBalance(int $type, float $debit, float $credit): float
    {
        // Asset & Expense: saldo normal debit; lainnya: kredit
        return in_array($type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE])
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }

    // helper untuk export (tetap pakai milikmu)
    private function signed(float $d, float $c, int $type): float
    {
        return in_array($type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE])
            ? round($d - $c, 2) : round($c - $d, 2);
    }

    /* ===========================
     * EXPORTS (punyamu — dipertahankan)
     * =========================== */
    public function exportLedgerCsv(Request $r): StreamedResponse
    {
        $accountId = (int) $r->input('account_id');
        if (!$accountId) abort(422, 'Parameter account_id wajib diisi');
        $start = $r->input('start');
        $end   = $r->input('end');
        $account = Account::findOrFail($accountId);

        $qOpen = JournalLine::where('account_id',$account->id);
        if ($start) $qOpen->whereHas('entry', fn($q)=>$q->where('date','<',$start));
        $opening = $this->signed((float)$qOpen->sum('debit'), (float)$qOpen->sum('credit'), $account->type);

        $q = JournalLine::with('entry')->where('account_id',$account->id);
        if ($start) $q->whereHas('entry', fn($q)=>$q->where('date','>=',$start));
        if ($end)   $q->whereHas('entry', fn($q)=>$q->where('date','<=',$end));
        $lines = $q->get()->sortBy(fn($l)=>sprintf('%s-%09d',$l->entry->date->format('Ymd'),$l->entry->id))->values();

        $filename = 'ledger_'.$account->code.'_'.date('Ymd_His').'.csv';
        return new StreamedResponse(function() use ($lines,$opening,$account){
            $out = fopen('php://output','w');
            fputcsv($out, ['Tanggal','Ref','Keterangan','Debit','Kredit','Saldo']);
            $running = $opening;
            foreach ($lines as $l) {
                $d=(float)$l->debit; $c=(float)$l->credit;
                if (in_array($account->type,[Account::TYPE_ASSET, Account::TYPE_EXPENSE])) $running += $d-$c; else $running += $c-$d;
                fputcsv($out, [$l->entry->date->format('Y-m-d'), $l->entry->ref, $l->entry->description, number_format($d,2,'.',''), number_format($c,2,'.',''), number_format($running,2,'.','')]);
            }
            fclose($out);
        }, 200, ['Content-Type'=>'text/csv', 'Content-Disposition'=>"attachment; filename=\"$filename\""]);
    }

    public function exportCashLedgerCsv(Request $r): StreamedResponse
    {
        $accountId = (int) $r->input('account_id');
        if (!$accountId) abort(422, 'Parameter account_id wajib diisi');
        $start = $r->input('start');
        $end   = $r->input('end');
        $account = Account::findOrFail($accountId);

        $qOpen = JournalLine::where('account_id',$account->id);
        if ($start) $qOpen->whereHas('entry', fn($q)=>$q->where('date','<',$start));
        $opening = $this->signed((float)$qOpen->sum('debit'), (float)$qOpen->sum('credit'), $account->type);

        $q = JournalLine::with('entry')->where('account_id',$account->id);
        if ($start) $q->whereHas('entry', fn($q)=>$q->where('date','>=',$start));
        if ($end)   $q->whereHas('entry', fn($q)=>$q->where('date','<=',$end));
        $lines = $q->get()->sortBy(fn($l)=>sprintf('%s-%09d',$l->entry->date->format('Ymd'),$l->entry->id))->values();

        $filename = 'cash_ledger_'.$account->code.'_'.date('Ymd_His').'.csv';
        return new StreamedResponse(function() use ($lines,$opening){
            $out = fopen('php://output','w');
            fputcsv($out, ['Tanggal','Ref','Keterangan','Masuk','Keluar','Saldo']);
            $running = $opening;
            foreach ($lines as $l) {
                $in=(float)$l->debit; $outv=(float)$l->credit;
                $running += $in-$outv;
                fputcsv($out, [$l->entry->date->format('Y-m-d'), $l->entry->ref, $l->entry->description, number_format($in,2,'.',''), number_format($outv,2,'.',''), number_format($running,2,'.','')]);
            }
            fclose($out);
        }, 200, ['Content-Type'=>'text/csv', 'Content-Disposition'=>"attachment; filename=\"$filename\""]);
    }

    public function exportTrialBalanceCsv(Request $r): StreamedResponse
    {
        $start=$r->input('start'); $end=$r->input('end');
        $accounts = Account::where('is_active',true)->orderBy('code')->get();
        $filename = 'trial_balance_'.date('Ymd_His').'.csv';

        return new StreamedResponse(function() use ($accounts,$start,$end){
            $out=fopen('php://output','w');
            fputcsv($out,['Kode','Nama Akun','Debit','Kredit']);
            foreach ($accounts as $a){
                $q=JournalLine::where('account_id',$a->id);
                if ($start) $q->whereHas('entry',fn($q)=>$q->where('date','>=',$start));
                if ($end)   $q->whereHas('entry',fn($q)=>$q->where('date','<=',$end));
                $d=(float)$q->sum('debit'); $c=(float)$q->sum('credit');
                $bal=$this->signed($d,$c,$a->type);
                $de=$cr=0.0;
                if (in_array($a->type,[Account::TYPE_ASSET,Account::TYPE_EXPENSE])) { $de = $bal>=0? $bal : 0; $cr = $bal<0? abs($bal):0; }
                else { $cr = $bal>=0? $bal : 0; $de = $bal<0? abs($bal):0; }
                fputcsv($out, [$a->code,$a->name,number_format($de,2,'.',''),number_format($cr,2,'.','')]);
            }
            fclose($out);
        },200,['Content-Type'=>'text/csv','Content-Disposition'=>"attachment; filename=\"$filename\""]);
    }

    public function exportTrialBalancePdf(Request $r)
    {
        $start=$r->input('start'); $end=$r->input('end');
        $accounts = Account::where('is_active',true)->orderBy('code')->get();
        $rows=[]; $TD=0; $TC=0;
        foreach($accounts as $a){
            $q=JournalLine::where('account_id',$a->id);
            if ($start) $q->whereHas('entry',fn($q)=>$q->where('date','>=',$start));
            if ($end)   $q->whereHas('entry',fn($q)=>$q->where('date','<=',$end));
            $d=(float)$q->sum('debit'); $c=(float)$q->sum('credit');
            $bal=$this->signed($d,$c,$a->type);
            $de=$cr=0.0;
            if (in_array($a->type,[Account::TYPE_ASSET,Account::TYPE_EXPENSE])) { $de = $bal>=0? $bal : 0; $cr = $bal<0? abs($bal):0; }
            else { $cr = $bal>=0? $bal : 0; $de = $bal<0? abs($bal):0; }
            $rows[]=['code'=>$a->code,'name'=>$a->name,'debit'=>$de,'credit'=>$cr];
            $TD+=$de; $TC+=$cr;
        }
        $pdf = PDF::loadView('finance.reports.pdf.trial_balance', compact('rows','TD','TC','start','end'))
                  ->setPaper('a4','portrait');
        return $pdf->download('trial_balance_'.date('Ymd_His').'.pdf');
    }

    public function exportBalanceSheetCsv(Request $r): StreamedResponse
    {
        $as = $r->input('as_of');
        $filename = 'balance_sheet_'.($as ?: date('Ymd')).'_'.date('His').'.csv';
        $acc = Account::where('is_active',true)->orderBy('code')->get();
        return new StreamedResponse(function() use ($acc,$as){
            $out=fopen('php://output','w');
            fputcsv($out,['Kelompok','Kode','Nama','Saldo']);
            $rev=$exp=0.0; $sum=['assets'=>0,'liabilities'=>0,'equity'=>0];
            foreach($acc as $a){
                $q=JournalLine::where('account_id',$a->id);
                if ($as) $q->whereHas('entry',fn($q)=>$q->where('date','<=',$as));
                $d=(float)$q->sum('debit'); $c=(float)$q->sum('credit'); $bal=$this->signed($d,$c,$a->type);
                $grp='';
                switch($a->type){
                    case Account::TYPE_ASSET:   $grp='ASSET'; $sum['assets']+=$bal; break;
                    case Account::TYPE_LIAB:    $grp='LIABILITY'; $sum['liabilities']+=$bal; break;
                    case Account::TYPE_EQUITY:  $grp='EQUITY'; $sum['equity']+=$bal; break;
                    case Account::TYPE_REVENUE: $grp='REVENUE'; $rev+=$bal; break;
                    case Account::TYPE_EXPENSE: $grp='EXPENSE'; $exp+=$bal; break;
                }
                if ($grp) fputcsv($out, [$grp,$a->code,$a->name,number_format($bal,2,'.','')]);
            }
            $ni=$rev-$exp; $totalRight=$sum['liabilities']+$sum['equity']+$ni;
            fputcsv($out, ['TOTAL ASSET','','',number_format($sum['assets'],2,'.','')]);
            fputcsv($out, ['TOTAL LIAB+EQUITY (incl. NI)','','',number_format($totalRight,2,'.','')]);
            fclose($out);
        },200,['Content-Type'=>'text/csv','Content-Disposition'=>"attachment; filename=\"$filename\""]);
    }

    public function exportBalanceSheetPdf(Request $r)
    {
        $as = $r->input('as_of');
        $grp=['assets'=>[],'liabilities'=>[],'equity'=>[]]; $sum=['assets'=>0,'liabilities'=>0,'equity'=>0];
        $acc=Account::where('is_active',true)->orderBy('code')->get(); $rev=0; $exp=0;
        foreach($acc as $a){
            $q=JournalLine::where('account_id',$a->id);
            if ($as) $q->whereHas('entry',fn($q)=>$q->where('date','<=',$as));
            $d=(float)$q->sum('debit'); $c=(float)$q->sum('credit'); $bal=$this->signed($d,$c,$a->type);
            switch($a->type){
                case Account::TYPE_ASSET:   $grp['assets'][]=[$a,$bal];      $sum['assets']+=$bal; break;
                case Account::TYPE_LIAB:    $grp['liabilities'][]=[$a,$bal]; $sum['liabilities']+=$bal; break;
                case Account::TYPE_EQUITY:  $grp['equity'][]=[$a,$bal];      $sum['equity']+=$bal; break;
                case Account::TYPE_REVENUE: $rev+=$bal; break;
                case Account::TYPE_EXPENSE: $exp+=$bal; break;
            }
        }
        $ni=$rev-$exp; $sum['equity']+=$ni;
        $pdf=PDF::loadView('finance.reports.pdf.balance_sheet', ['grouped'=>$grp,'sum'=>$sum,'netIncome'=>$ni,'asOf'=>$as])
                ->setPaper('a4','portrait');
        return $pdf->download('balance_sheet_'.($as ?: date('Ymd')).'_'.date('His').'.pdf');
    }
}
