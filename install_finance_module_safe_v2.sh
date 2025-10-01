#!/usr/bin/env bash
# ============================================================================
# Finance Module SAFE Installer v2  (Laravel 7.x / PHP 7.4)
# ----------------------------------------------------------------------------
# Tujuan: memasang modul Finance **sangat aman & idempotent** tanpa merusak
# existing code. v2 ini **tanpa** konstruk `read -r -d ''` agar kompatibel
# dengan lebih banyak shell. Semua template ditulis via heredoc stdin.
#
# Fitur yang dipasang:
# - Tabel: accounts, journal_entries, journal_lines (migration dengan guard)
# - Models: Account, JournalEntry, JournalLine
# - Controllers: AccountController, JournalController (+Jurnal Umum),
#                ReportController (Buku Besar, Lajur Kas, Trial Balance, Neraca)
# - Routes: routes/finance.php + loader aman di routes/web.php
# - Views: accounts, kas, reports (lengkap) + jurnal_umum
# - Seeder: AccountsTableSeeder (PSR-4 / legacy aware) + inject ke DatabaseSeeder
# - composer dump-autoload -o, opsi migrate/seed (prompt)
# ============================================================================
set -euo pipefail
IFS=$'\n\t'

TS=$(date +%Y%m%d%H%M%S)
ASSUME_YES=false
DRYRUN=false
RUN_MIGRATE=true
RUN_SEED=true
ENABLE_ADDONS=true        # Jurnal Umum
FORCE_REPLACE=false
PHP_BIN=${PHP_BIN:-php}
COMPOSER_BIN=${COMPOSER_BIN:-composer}

say(){ printf "\033[1;36m[finance]\033[0m %s\n" "$*"; }
warn(){ printf "\033[1;33m[warn]\033[0m %s\n" "$*"; }
err(){ printf "\033[1;31m[err]\033[0m %s\n" "$*"; }
ask(){ local q="$1"; local d="$2"; $ASSUME_YES && { echo "$d"; return; } ; read -rp "$q [$d] " a; echo "${a:-$d}"; }

ensure_dir(){ $DRYRUN && { say "mkdir -p $1"; return; } ; mkdir -p "$1"; }
backup(){ local f="$1"; [[ -f "$f" ]] || return 0; $DRYRUN && { say "backup $f -> $f.bak.$TS"; return; } ; cp "$f" "$f.bak.$TS"; }

is_php_file(){ [[ "$1" == *.php ]] && [[ "$1" != *".blade.php"* ]]; }
php_lint(){ local f="$1"; is_php_file "$f" || return 0; $DRYRUN && { say "php -l $f"; return; } ; $PHP_BIN -l "$f" >/dev/null; }

write_file(){ # write_file <path>  (content via stdin)
  local path="$1"; shift || true
  ensure_dir "$(dirname "$path")"
  local content; content="$(cat)"
  if [[ -f "$path" ]]; then
    if cmp -s <(printf "%s" "$content") "$path"; then say "skip (identik): $path"; return; fi
    local choice="S"; $FORCE_REPLACE && choice="R"
    $DRYRUN && { say "REPLACE $path"; return; }
    if [[ "$choice" != "R" ]]; then choice=$(ask "File $path sudah ada. Replace? (R)ep lace/(S)kip" "S"); fi
    if [[ "$choice" =~ ^[Rr]$ ]]; then backup "$path"; printf "%s" "$content" > "$path"; php_lint "$path"; say "replace $path"; else say "skip: $path"; fi
    return
  fi
  $DRYRUN && { say "create $path"; return; }
  printf "%s" "$content" > "$path"; php_lint "$path"; say "create $path";
}

append_once(){ # append_once <path> <needle>  (content via stdin)
  local path="$1"; local needle="$2"; shift 2 || true
  ensure_dir "$(dirname "$path")"
  [[ -f "$path" ]] || { $DRYRUN || touch "$path"; }
  if grep -Fq "$needle" "$path" 2>/dev/null; then say "skip append (ada): $needle -> $path"; return; fi
  local payload; payload="$(cat)"
  $DRYRUN && { say "append -> $path (needle: $needle)"; return; }
  backup "$path"; printf "\n%s\n" "$payload" >> "$path"; php_lint "$path"; say "append $needle -> $path"
}

# --------------------------- ARGS ---------------------------------------------
while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRYRUN=true; shift;;
    --yes|-y) ASSUME_YES=true; shift;;
    --no-migrate) RUN_MIGRATE=false; shift;;
    --no-seed) RUN_SEED=false; shift;;
    --no-addons) ENABLE_ADDONS=false; shift;;
    --force-replace) FORCE_REPLACE=true; shift;;
    *) warn "arg tidak dikenal: $1"; shift;;
  esac
done

# --------------------------- PRECHECKS ----------------------------------------
if [[ ! -f artisan ]]; then err "Jalankan dari root Laravel (file artisan tidak ada)"; exit 1; fi
command -v "$PHP_BIN" >/dev/null || { err "php tidak ditemukan di PATH"; exit 1; }
command -v "$COMPOSER_BIN" >/dev/null || warn "composer tidak ditemukan; akan coba jalankan 'composer' langsung"

say "Mulai instalasi SAFE v2 (addons: $ENABLE_ADDONS, dry-run: $DRYRUN, force: $FORCE_REPLACE)"

# --------------------------- CONFIG ------------------------------------------
write_file config/finance.php <<'PHP'
<?php
return [
    'default_cash_account_id' => null,
];
PHP

# --------------------------- MIGRATION ---------------------------------------
ensure_dir database/migrations
MIG_FILE=$(ls database/migrations/*_create_finance_tables.php 2>/dev/null | head -n1 || true)
if [[ -z "${MIG_FILE}" ]]; then
  MIG_FILE="database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_finance_tables.php"
  write_file "$MIG_FILE" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
class CreateFinanceTables extends Migration{ public function up(){
  if(!Schema::hasTable('accounts')){ Schema::create('accounts',function(Blueprint $t){ $t->bigIncrements('id'); $t->string('code')->unique(); $t->string('name'); $t->unsignedTinyInteger('type'); $t->unsignedBigInteger('parent_id')->nullable(); $t->boolean('is_cash')->default(false); $t->boolean('is_active')->default(true); $t->timestamps(); $t->foreign('parent_id')->references('id')->on('accounts')->onDelete('set null'); }); }
  if(!Schema::hasTable('journal_entries')){ Schema::create('journal_entries',function(Blueprint $t){ $t->bigIncrements('id'); $t->date('date'); $t->string('ref')->nullable(); $t->text('description')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamp('posted_at')->nullable(); $t->timestamps(); $t->index(['date']); }); }
  if(!Schema::hasTable('journal_lines')){ Schema::create('journal_lines',function(Blueprint $t){ $t->bigIncrements('id'); $t->unsignedBigInteger('journal_entry_id'); $t->unsignedBigInteger('account_id'); $t->decimal('debit',18,2)->default(0); $t->decimal('credit',18,2)->default(0); $t->string('memo')->nullable(); $t->timestamps(); $t->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade'); $t->foreign('account_id')->references('id')->on('accounts'); $t->index(['account_id']); }); }
} public function down(){ Schema::dropIfExists('journal_lines'); Schema::dropIfExists('journal_entries'); Schema::dropIfExists('accounts'); } }
PHP
else
  say "migration sudah ada: $MIG_FILE (skip)"
fi

# --------------------------- MODELS ------------------------------------------
[[ -f app/Models/Account.php ]] || write_file app/Models/Account.php <<'PHP'
<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Account extends Model{ protected $fillable=['code','name','type','parent_id','is_cash','is_active']; const TYPE_ASSET=1,TYPE_LIAB=2,TYPE_EQUITY=3,TYPE_REVENUE=4,TYPE_EXPENSE=5; function parent(){return $this->belongsTo(Account::class,'parent_id');} function children(){return $this->hasMany(Account::class,'parent_id');} function lines(){return $this->hasMany(JournalLine::class);} function typeLabel(){return [1=>'Asset',2=>'Liability',3=>'Equity',4=>'Revenue',5=>'Expense'][$this->type]??'Unknown';} }
PHP
[[ -f app/Models/JournalEntry.php ]] || write_file app/Models/JournalEntry.php <<'PHP'
<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class JournalEntry extends Model{ protected $fillable=['date','ref','description','created_by','posted_at']; protected $dates=['date','posted_at']; function lines(){return $this->hasMany(JournalLine::class);} }
PHP
[[ -f app/Models/JournalLine.php ]] || write_file app/Models/JournalLine.php <<'PHP'
<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class JournalLine extends Model{ protected $fillable=['journal_entry_id','account_id','debit','credit','memo']; function entry(){return $this->belongsTo(JournalEntry::class,'journal_entry_id');} function account(){return $this->belongsTo(Account::class,'account_id');} }
PHP

# --------------------------- CONTROLLERS -------------------------------------
ensure_dir app/Http/Controllers/Finance
[[ -f app/Http/Controllers/Finance/AccountController.php ]] || write_file app/Http/Controllers/Finance/AccountController.php <<'PHP'
<?php
namespace App\Http\Controllers\Finance; use App\Http\Controllers\Controller; use App\Models\Account; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
class AccountController extends Controller{ function index(){ $accounts=Account::orderBy('code')->get(); $parents=Account::orderBy('code')->get(); return view('finance.accounts.index',compact('accounts','parents')); } function store(Request $r){ $d=$r->validate(['code'=>'required|string|max:50|unique:accounts,code','name'=>'required|string|max:150','type'=>'required|in:1,2,3,4,5','parent_id'=>'nullable|exists:accounts,id','is_cash'=>'nullable|boolean','is_active'=>'nullable|boolean']); $d['is_cash']=(bool)($d['is_cash']??false); $d['is_active']=(bool)($d['is_active']??true); Account::create($d); return back()->with('ok','Akun ditambahkan'); } function update(Request $r, Account $account){ $d=$r->validate(['code'=>'required|string|max:50|unique:accounts,code,'.$account->id,'name'=>'required|string|max:150','type'=>'required|in:1,2,3,4,5','parent_id'=>'nullable|exists:accounts,id','is_cash'=>'nullable|boolean','is_active'=>'nullable|boolean']); $d['is_cash']=(bool)($d['is_cash']??false); $d['is_active']=(bool)($d['is_active']??true); $account->update($d); return back()->with('ok','Akun diupdate'); } function destroy(Account $account){ DB::transaction(function() use($account){ if($account->lines()->exists()) abort(422,'Akun memiliki transaksi, tidak dapat dihapus.'); $account->delete(); }); return back()->with('ok','Akun dihapus'); } }
PHP

# JournalController (dengan Jurnal Umum)
if [[ ! -f app/Http/Controllers/Finance/JournalController.php ]]; then
  write_file app/Http/Controllers/Finance/JournalController.php <<'PHP'
<?php
namespace App\Http\Controllers\Finance; use App\Http\Controllers\Controller; use App\Models\{Account,JournalEntry,JournalLine}; use Illuminate\Http\Request; use Illuminate\Support\Facades\{Auth,DB};
class JournalController extends Controller{ function kas(){ $cashAccounts=Account::where('is_cash',true)->orderBy('code')->get(); $allAccounts=Account::orderBy('code')->get(); $recent=JournalEntry::with(['lines.account'])->orderBy('date','desc')->orderBy('id','desc')->limit(25)->get(); return view('finance.journal.kas',compact('cashAccounts','allAccounts','recent')); } function storeKas(Request $r){ $d=$r->validate(['date'=>'required|date','type'=>'required|in:in,out','cash_account_id'=>'required|exists:accounts,id','counter_account_id'=>'required|exists:accounts,id','amount'=>'required|numeric|min:0.01','description'=>'nullable|string','ref'=>'nullable|string|max:50']); $cash=Account::findOrFail($d['cash_account_id']); if(!$cash->is_cash) return back()->withErrors(['cash_account_id'=>'Akun dipilih bukan akun kas/bank.']); DB::transaction(function() use($d){ $e=JournalEntry::create(['date'=>$d['date'],'ref'=>$d['ref']??null,'description'=>$d['description']??null,'created_by'=>Auth::id(),'posted_at'=>now()]); $cid=(int)$d['counter_account_id']; $amt=round((float)$d['amount'],2); if($d['type']==='in'){ JournalLine::create(['journal_entry_id'=>$e->id,'account_id'=>(int)$d['cash_account_id'],'debit'=>$amt,'credit'=>0,'memo'=>'Kas Masuk']); JournalLine::create(['journal_entry_id'=>$e->id,'account_id'=>$cid,'debit'=>0,'credit'=>$amt,'memo'=>'Kas Masuk (lawan)']); } else { JournalLine::create(['journal_entry_id'=>$e->id,'account_id'=>$cid,'debit'=>$amt,'credit'=>0,'memo'=>'Kas Keluar']); JournalLine::create(['journal_entry_id'=>$e->id,'account_id'=>(int)$d['cash_account_id'],'debit'=>0,'credit'=>$amt,'memo'=>'Kas Keluar (lawan)']); } }); return back()->with('ok','Transaksi kas tersimpan.'); } function jurnalUmum(){ $accounts=Account::orderBy('code')->get(); return view('finance.journal.jurnal_umum',compact('accounts')); } function storeJurnalUmum(Request $r){ $d=$r->validate(['date'=>'required|date','ref'=>'nullable|string|max:50','description'=>'nullable|string','lines'=>'required|array|min:2','lines.*.account_id'=>'required|exists:accounts,id','lines.*.debit'=>'nullable|numeric|min:0','lines.*.credit'=>'nullable|numeric|min:0','lines.*.memo'=>'nullable|string|max:255']); $D=0; $C=0; $clean=[]; foreach($d['lines'] as $i=>$l){ $dd=round((float)($l['debit']??0),2); $cc=round((float)($l['credit']??0),2); if($dd>0 && $cc>0) return back()->withErrors('Baris '.($i+1).': tidak boleh debit & kredit sekaligus.')->withInput(); if($dd==0 && $cc==0) continue; $clean[]=['account_id'=>(int)$l['account_id'],'debit'=>$dd,'credit'=>$cc,'memo'=>$l['memo']??null]; $D+=$dd; $C+=$cc; } if(count($clean)<2) return back()->withErrors('Minimal 2 baris (debit & kredit).')->withInput(); if(abs($D-$C)>0.009) return back()->withErrors('Total debit harus sama dengan total kredit.')->withInput(); DB::transaction(function() use($d,$clean){ $e=JournalEntry::create(['date'=>$d['date'],'ref'=>$d['ref']??null,'description'=>$d['description']??null,'created_by'=>Auth::id(),'posted_at'=>now()]); foreach($clean as $l){ JournalLine::create(['journal_entry_id'=>$e->id]+$l); } }); return back()->with('ok','Jurnal umum tersimpan.'); } }
PHP
else
  say "JournalController sudah ada (skip)"
fi

[[ -f app/Http/Controllers/Finance/ReportController.php ]] || write_file app/Http/Controllers/Finance/ReportController.php <<'PHP'
<?php
namespace App\Http\Controllers\Finance; use App\Http\Controllers\Controller; use App\Models\{Account,JournalLine}; use Illuminate\Http\Request;
class ReportController extends Controller{ static function signedBalance(int $t,float $d,float $c){return in_array($t,[1,5])?round($d-$c,2):round($c-$d,2);} function ledger(Request $r){ $id=(int)$r->input('account_id'); $start=$r->input('start'); $end=$r->input('end'); $accounts=Account::orderBy('code')->get(); $account=$id?Account::find($id):null; $lines=collect(); $opening=0.0; if($account){ $qOpen=JournalLine::where('account_id',$account->id); if($start) $qOpen->whereHas('entry',fn($q)=>$q->where('date','<',$start)); $opening=self::signedBalance($account->type,(float)$qOpen->sum('debit'),(float)$qOpen->sum('credit')); $q=JournalLine::with('entry')->where('account_id',$account->id); if($start) $q->whereHas('entry',fn($qq)=>$qq->where('date','>=',$start)); if($end) $q->whereHas('entry',fn($qq)=>$qq->where('date','<=',$end)); $lines=$q->get()->sortBy(fn($l)=>sprintf('%s-%09d',$l->entry->date->format('Ymd'),$l->entry->id))->values(); } return view('finance.reports.ledger',compact('accounts','account','lines','opening','start','end')); } function cashLedger(Request $r){ $cash=Account::where('is_cash',true)->orderBy('code')->get(); $id=(int)$r->input('account_id'); $start=$r->input('start'); $end=$r->input('end'); $account=$id?Account::find($id):$cash->first(); $lines=collect(); $opening=0.0; if($account){ $qOpen=JournalLine::where('account_id',$account->id); if($start) $qOpen->whereHas('entry',fn($q)=>$q->where('date','<',$start)); $opening=self::signedBalance($account->type,(float)$qOpen->sum('debit'),(float)$qOpen->sum('credit')); $q=JournalLine::with('entry')->where('account_id',$account->id); if($start) $q->whereHas('entry',fn($qq)=>$qq->where('date','>=',$start)); if($end) $q->whereHas('entry',fn($qq)=>$qq->where('date','<=',$end)); $lines=$q->get()->sortBy(fn($l)=>sprintf('%s-%09d',$l->entry->date->format('Ymd'),$l->entry->id))->values(); } return view('finance.reports.cash_ledger',compact('cash','account','lines','opening','start','end'))->with('cashAccounts',$cash); } function trialBalance(Request $r){ $start=$r->input('start'); $end=$r->input('end'); $accounts=Account::where('is_active',true)->orderBy('code')->get(); $rows=[]; $TD=0; $TC=0; foreach($accounts as $a){ $q=JournalLine::where('account_id',$a->id); if($start) $q->whereHas('entry',fn($qq)=>$qq->where('date','>=',$start)); if($end) $q->whereHas('entry',fn($qq)=>$qq->where('date','<=',$end)); $d=(float)$q->sum('debit'); $c=(float)$q->sum('credit'); $bal=self::signedBalance($a->type,$d,$c); $de=0; $cr=0; if(in_array($a->type,[1,5])){ if($bal>=0){$de=$bal;} else {$cr=abs($bal);} } else { if($bal>=0){$cr=$bal;} else {$de=abs($bal);} } $rows[]=['a'=>$a,'debitCol'=>$de,'creditCol'=>$cr]; $TD+=$de; $TC+=$cr; } return view('finance.reports.trial_balance',compact('rows','TD','TC','start','end'))->with(['totalDebit'=>$TD,'totalCredit'=>$TC]); } function balanceSheet(Request $r){ $as=$r->input('as_of'); $grp=['assets'=>[],'liabilities'=>[],'equity'=>[]]; $sum=['assets'=>0,'liabilities'=>0,'equity'=>0]; $acc=Account::where('is_active',true)->orderBy('code')->get(); $rev=0; $exp=0; foreach($acc as $a){ $q=JournalLine::where('account_id',$a->id); if($as) $q->whereHas('entry',fn($qq)=>$qq->where('date','<=',$as)); $d=(float)$q->sum('debit'); $c=(float)$q->sum('credit'); $bal=self::signedBalance($a->type,$d,$c); switch($a->type){ case 1:$grp['assets'][]=[$a,$bal];$sum['assets']+=$bal;break; case 2:$grp['liabilities'][]=[$a,$bal];$sum['liabilities']+=$bal;break; case 3:$grp['equity'][]=[$a,$bal];$sum['equity']+=$bal;break; case 4:$rev+=$bal;break; case 5:$exp+=$bal;break; } } $ni=$rev-$exp; $sum['equity']+=$ni; return view('finance.reports.balance_sheet',compact('grp','sum','ni','as'))->with(['grouped'=>$grp,'netIncome'=>$ni,'asOf'=>$as]); } }
PHP

# --------------------------- ROUTES ------------------------------------------
[[ -f routes/finance.php ]] || write_file routes/finance.php <<'PHP'
<?php
use Illuminate\Support\Facades\Route; use App\Http\Controllers\Finance\{AccountController,JournalController,ReportController};
Route::middleware(['web','auth'])->prefix('finance')->name('finance.')->group(function(){
  Route::get('/accounts',[AccountController::class,'index'])->name('accounts');
  Route::post('/accounts',[AccountController::class,'store'])->name('accounts.store');
  Route::post('/accounts/{account}',[AccountController::class,'update'])->name('accounts.update');
  Route::delete('/accounts/{account}',[AccountController::class,'destroy'])->name('accounts.delete');
  Route::get('/kas',[JournalController::class,'kas'])->name('kas');
  Route::post('/kas',[JournalController::class,'storeKas'])->name('kas.store');
  Route::get('/ledger',[ReportController::class,'ledger'])->name('ledger');
  Route::get('/cash-ledger',[ReportController::class,'cashLedger'])->name('cash');
  Route::get('/trial-balance',[ReportController::class,'trialBalance'])->name('trial');
  Route::get('/balance-sheet',[ReportController::class,'balanceSheet'])->name('balance');
  Route::get('/jurnal-umum',[JournalController::class,'jurnalUmum'])->name('jurnal');
  Route::post('/jurnal-umum',[JournalController::class,'storeJurnalUmum'])->name('jurnal.store');
}); // FINANCE_ROUTE_GROUP
PHP

# loader di routes/web.php (idempotent)
append_once routes/web.php FINANCE_MODULE_LOADER <<'PHP'
// >>> FINANCE_MODULE_LOADER
if (file_exists(base_path('routes/finance.php'))) {
    require base_path('routes/finance.php');
}
// <<< FINANCE_MODULE_LOADER
PHP

# --------------------------- VIEWS -------------------------------------------
ensure_dir resources/views/finance/accounts resources/views/finance/journal resources/views/finance/reports

[[ -f resources/views/finance/accounts/index.blade.php ]] || write_file resources/views/finance/accounts/index.blade.php <<'BLADE'
@extends('layouts.app')
@section('title','Finance — Nomor Akun')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="flex items-center justify-between mb-4"><div class="text-lg text-slate-200 font-semibold">Nomor Akun (Chart of Accounts)</div></div>
  @if(session('ok'))<div class="text-green-400 mb-3">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="text-red-400 mb-3">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('finance.accounts.store') }}" class="grid md:grid-cols-6 gap-3 mb-6">@csrf
    <input class="m-inp md:col-span-1" name="code" placeholder="Kode" required>
    <input class="m-inp md:col-span-2" name="name" placeholder="Nama Akun" required>
    <select class="m-inp md:col-span-1" name="type" required>
      <option value="">Tipe</option><option value="1">Asset</option><option value="2">Liability</option><option value="3">Equity</option><option value="4">Revenue</option><option value="5">Expense</option>
    </select>
    <select class="m-inp md:col-span-1" name="parent_id"><option value="">Parent (opsional)</option>@foreach($parents as $p)<option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>@endforeach</select>
    <div class="flex items-center gap-4 md:col-span-1">
      <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_cash" value="1"> <span>Kas/Bank</span></label>
      <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> <span>Aktif</span></label>
    </div>
    <div class="md:col-span-6"><button class="m-btn">Tambah Akun</button></div>
  </form>
  <div class="overflow-auto">
    <table class="w-full text-sm"><thead class="text-slate-300"><tr><th class="text-left p-2">Kode</th><th class="text-left p-2">Nama</th><th class="text-left p-2">Tipe</th><th class="text-left p-2">Kas</th><th class="text-left p-2">Aktif</th><th class="text-left p-2">Parent</th><th class="p-2">Aksi</th></tr></thead>
      <tbody>
      @forelse($accounts as $a)
        <tr class="border-t border-slate-700">
          <td class="p-2">{{ $a->code }}</td><td class="p-2">{{ $a->name }}</td><td class="p-2">{{ $a->typeLabel() }}</td>
          <td class="p-2">{{ $a->is_cash ? 'Ya' : '-' }}</td><td class="p-2">{{ $a->is_active ? 'Ya' : '-' }}</td>
          <td class="p-2">{{ optional($a->parent)->code }}</td>
          <td class="p-2">
            <details>
              <summary class="cursor-pointer text-blue-300">Edit</summary>
              <form method="POST" action="{{ route('finance.accounts.update',$a) }}" class="grid md:grid-cols-6 gap-2 mt-2">@csrf
                <input class="m-inp" name="code" value="{{ $a->code }}">
                <input class="m-inp md:col-span-2" name="name" value="{{ $a->name }}">
                <select class="m-inp" name="type">@for($i=1;$i<=5;$i++)<option value="{{ $i }}" @if($a->type==$i) selected @endif>{{ ['','Asset','Liability','Equity','Revenue','Expense'][$i] }}</option>@endfor</select>
                <select class="m-inp" name="parent_id"><option value="">(none)</option>@foreach($parents as $p)<option value="{{ $p->id }}" @if($a->parent_id==$p->id) selected @endif>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select>
                <div class="flex gap-4 items-center"><label class="inline-flex items-center gap-2"><input type="checkbox" name="is_cash" value="1" @if($a->is_cash) checked @endif> <span>Kas</span></label><label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @if($a->is_active) checked @endif> <span>Aktif</span></label></div>
                <div class="md:col-span-6 flex gap-2"><button class="m-btn">Simpan</button></div>
              </form>
              <form method="POST" action="{{ route('finance.accounts.delete',$a) }}" onsubmit="return confirm('Hapus akun {{ $a->code }}?')" class="mt-2">@csrf @method('DELETE')<button class="m-btnp">Hapus</button></form>
            </details>
          </td>
        </tr>
      @empty
        <tr><td class="p-3" colspan="7">Belum ada akun.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
BLADE

[[ -f resources/views/finance/journal/kas.blade.php ]] || write_file resources/views/finance/journal/kas.blade.php <<'BLADE'
@extends('layouts.app')
@section('title','Finance — Kas Keluar/Masuk')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Kas Keluar/Masuk</div>
  @if(session('ok'))<div class="text-green-400 mb-3">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="text-red-400 mb-3">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('finance.kas.store') }}" class="grid md:grid-cols-6 gap-3">@csrf
    <input type="date" name="date" class="m-inp" required value="{{ now()->toDateString() }}">
    <select name="type" class="m-inp" required><option value="in">Kas Masuk</option><option value="out">Kas Keluar</option></select>
    <select name="cash_account_id" class="m-inp" required><option value="">Pilih Akun Kas/Bank</option>@foreach($cashAccounts as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach</select>
    <select name="counter_account_id" class="m-inp" required><option value="">Akun Lawan</option>@foreach($allAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select>
    <input type="number" step="0.01" min="0" name="amount" class="m-inp" placeholder="Jumlah" required>
    <input type="text" name="ref" class="m-inp" placeholder="Ref (opsional)">
    <div class="md:col-span-6"><input type="text" name="description" class="m-inp" placeholder="Keterangan (opsional)"></div>
    <div class="md:col-span-6"><button class="m-btn">Simpan</button></div>
  </form>
</div>
<div class="m-card p-5">
  <div class="text-slate-200 font-semibold mb-3">Transaksi Terbaru</div>
  <div class="overflow-auto"><table class="w-full text-sm"><thead><tr><th class="text-left p-2">Tanggal</th><th class="text-left p-2">Ref</th><th class="text-left p-2">Deskripsi</th><th class="text-right p-2">Debit</th><th class="text-right p-2">Kredit</th></tr></thead>
    <tbody>@forelse($recent as $e) @php $debit=$e->lines->sum('debit'); $credit=$e->lines->sum('credit'); @endphp
      <tr class="border-t border-slate-700"><td class="p-2">{{ $e->date->format('Y-m-d') }}</td><td class="p-2">{{ $e->ref }}</td><td class="p-2">{{ $e->description }}</td><td class="p-2 text-right">{{ number_format($debit,2) }}</td><td class="p-2 text-right">{{ number_format($credit,2) }}</td></tr>
    @empty <tr><td class="p-3" colspan="5">Belum ada transaksi.</td></tr> @endforelse</tbody></table></div>
</div>
@endsection
BLADE

[[ -f resources/views/finance/reports/ledger.blade.php ]] || write_file resources/views/finance/reports/ledger.blade.php <<'BLADE'
@extends('layouts.app')
@section('title','Finance — Buku Besar')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Buku Besar</div>
  <form method="GET" class="grid md:grid-cols-6 gap-3 mb-4">
    <select name="account_id" class="m-inp md:col-span-2" required>
      <option value="">Pilih Akun</option>
      @foreach($accounts as $a)
        <option value="{{ $a->id }}" @if(optional($account)->id==$a->id) selected @endif>{{ $a->code }} — {{ $a->name }}</option>
      @endforeach
    </select>
    <input type="date" name="start" class="m-inp" value="{{ $start }}">
    <input type="date" name="end" class="m-inp" value="{{ $end }}">
    <div class="md:col-span-2"><button class="m-btn">Tampilkan</button></div>
  </form>
  @if($account)
  <div class="text-slate-300 mb-2">Akun: <b>{{ $account->code }}</b> — {{ $account->name }}</div>
  <div class="overflow-auto">
    <table class="w-full text-sm"><thead><tr><th class="text-left p-2">Tanggal</th><th class="text-left p-2">Ref</th><th class="text-left p-2">Keterangan</th><th class="text-right p-2">Debit</th><th class="text-right p-2">Kredit</th><th class="text-right p-2">Saldo</th></tr></thead>
      <tbody>
        <tr class="bg-slate-800/40"><td class="p-2" colspan="5">Saldo Awal</td><td class="p-2 text-right">{{ number_format($opening,2) }}</td></tr>
        @php $running=$opening; @endphp
        @foreach($lines as $l) @php $d=(float)$l->debit; $c=(float)$l->credit; if(in_array($account->type,[1,5])){$running+=$d-$c;}else{$running+=$c-$d;} @endphp
          <tr class="border-t border-slate-700"><td class="p-2">{{ $l->entry->date->format('Y-m-d') }}</td><td class="p-2">{{ $l->entry->ref }}</td><td class="p-2">{{ $l->entry->description }}</td><td class="p-2 text-right">{{ number_format($d,2) }}</td><td class="p-2 text-right">{{ number_format($c,2) }}</td><td class="p-2 text-right">{{ number_format($running,2) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>
@endsection
BLADE

[[ -f resources/views/finance/reports/cash_ledger.blade.php ]] || write_file resources/views/finance/reports/cash_ledger.blade.php <<'BLADE'
@extends('layouts.app')
@section('title','Finance — Lajur Kas')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Lajur Kas</div>
  <form method="GET" class="grid md:grid-cols-6 gap-3 mb-4">
    <select name="account_id" class="m-inp md:col-span-2" required>
      @foreach($cashAccounts as $a)
        <option value="{{ $a->id }}" @if(optional($account)->id==$a->id) selected @endif>{{ $a->code }} — {{ $a->name }}</option>
      @endforeach
    </select>
    <input type="date" name="start" class="m-inp" value="{{ $start }}">
    <input type="date" name="end" class="m-inp" value="{{ $end }}">
    <div class="md:col-span-2"><button class="m-btn">Tampilkan</button></div>
  </form>
  @if($account)
  <div class="text-slate-300 mb-2">Akun Kas: <b>{{ $account->code }}</b> — {{ $account->name }}</div>
  <div class="overflow-auto"><table class="w-full text-sm"><thead><tr><th class="text-left p-2">Tanggal</th><th class="text-left p-2">Ref</th><th class="text-left p-2">Keterangan</th><th class="text-right p-2">Masuk</th><th class="text-right p-2">Keluar</th><th class="text-right p-2">Saldo</th></tr></thead>
    <tbody>
      <tr class="bg-slate-800/40"><td class="p-2" colspan="5">Saldo Awal</td><td class="p-2 text-right">{{ number_format($opening,2) }}</td></tr>
      @php $running=$opening; @endphp
      @foreach($lines as $l) @php $masuk=(float)$l->debit; $keluar=(float)$l->credit; $running+=$masuk-$keluar; @endphp
        <tr class="border-t border-slate-700"><td class="p-2">{{ $l->entry->date->format('Y-m-d') }}</td><td class="p-2">{{ $l->entry->ref }}</td><td class="p-2">{{ $l->entry->description }}</td><td class="p-2 text-right">{{ number_format($masuk,2) }}</td><td class="p-2 text-right">{{ number_format($keluar,2) }}</td><td class="p-2 text-right">{{ number_format($running,2) }}</td></tr>
      @endforeach
    </tbody></table></div>
  </div>
  @endif
</div>
@endsection
BLADE

[[ -f resources/views/finance/reports/trial_balance.blade.php ]] || write_file resources/views/finance/reports/trial_balance.blade.php <<'BLADE'
@extends('layouts.app')
@section('title','Finance — Neraca Percobaan')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Neraca Percobaan</div>
  <form method="GET" class="grid md:grid-cols-5 gap-3 mb-4">
    <input type="date" name="start" class="m-inp" value="{{ $start }}">
    <input type="date" name="end" class="m-inp" value="{{ $end }}">
    <div class="md:col-span-3"><button class="m-btn">Tampilkan</button></div>
  </form>
  <div class="overflow-auto"><table class="w-full text-sm"><thead><tr><th class="text-left p-2">Kode</th><th class="text-left p-2">Nama Akun</th><th class="text-right p-2">Debit</th><th class="text-right p-2">Kredit</th></tr></thead>
    <tbody>
      @foreach($rows as $row)
        <tr class="border-t border-slate-700"><td class="p-2">{{ $row['a']->code }}</td><td class="p-2">{{ $row['a']->name }}</td><td class="p-2 text-right">{{ number_format($row['debitCol'],2) }}</td><td class="p-2 text-right">{{ number_format($row['creditCol'],2) }}</td></tr>
      @endforeach
      <tr class="border-t border-slate-600 font-semibold"><td class="p-2" colspan="2">TOTAL</td><td class="p-2 text-right">{{ number_format($totalDebit,2) }}</td><td class="p-2 text-right">{{ number_format($totalCredit,2) }}</td></tr>
    </tbody></table></div>
</div>
@endsection
BLADE

[[ -f resources/views/finance/reports/balance_sheet.blade.php ]] || write_file resources/views/finance/reports/balance_sheet.blade.php <<'BLADE'
@extends('layouts.app')
@section('title','Finance — Neraca')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Neraca</div>
  <form method="GET" class="grid md:grid-cols-4 gap-3 mb-4"><input type="date" name="as_of" class="m-inp" value="{{ $asOf }}"><div class="md:col-span-3"><button class="m-btn">Tampilkan</button></div></form>
  <div class="grid md:grid-cols-2 gap-6">
    <div class="m-card p-4 bg-slate-900/40"><div class="font-semibold mb-2">ASSET</div><table class="w-full text-sm">@foreach($grouped['assets'] as [$a,$bal])<tr><td class="p-1">{{ $a->code }} — {{ $a->name }}</td><td class="p-1 text-right">{{ number_format($bal,2) }}</td></tr>@endforeach<tr class="border-t border-slate-600 font-semibold"><td class="p-1">TOTAL ASSET</td><td class="p-1 text-right">{{ number_format($sum['assets'],2) }}</td></tr></table></div>
    <div class="m-card p-4 bg-slate-900/40"><div class="font-semibold mb-2">LIABILITIES & EQUITY</div><table class="w-full text-sm">@foreach($grouped['liabilities'] as [$a,$bal])<tr><td class="p-1">{{ $a->code }} — {{ $a->name }}</td><td class="p-1 text-right">{{ number_format($bal,2) }}</td></tr>@endforeach @foreach($grouped['equity'] as [$a,$bal])<tr><td class="p-1">{{ $a->code }} — {{ $a->name }}</td><td class="p-1 text-right">{{ number_format($bal,2) }}</td></tr>@endforeach <tr><td class="p-1">Laba/Rugi Berjalan</td><td class="p-1 text-right">{{ number_format($netIncome,2) }}</td></tr><tr class="border-t border-slate-600 font-semibold"><td class="p-1">TOTAL LIAB + EQUITY</td><td class="p-1 text-right">{{ number_format($sum['liabilities'] + $sum['equity'],2) }}</td></tr></table></div>
  </div>
</div>
@endsection
BLADE

[[ -f resources/views/finance/journal/jurnal_umum.blade.php ]] || write_file resources/views/finance/journal/jurnal_umum.blade.php <<'BLADE'
@extends('layouts.app')
@section('title','Finance — Jurnal Umum')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Jurnal Umum</div>
  @if(session('ok'))<div class="text-green-400 mb-3">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="text-red-400 mb-3">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('finance.jurnal.store') }}">@csrf
    <div class="grid md:grid-cols-6 gap-3 mb-4">
      <input type="date" name="date" class="m-inp" required value="{{ now()->toDateString() }}">
      <input type="text" name="ref" class="m-inp" placeholder="Ref (opsional)">
      <div class="md:col-span-4"><input type="text" name="description" class="m-inp" placeholder="Keterangan (opsional)"></div>
    </div>
    <div class="overflow-auto">
      <table class="w-full text-sm" id="jr-table"><thead><tr><th class="text-left p-2 w-1/2">Akun</th><th class="text-right p-2 w-32">Debit</th><th class="text-right p-2 w-32">Kredit</th><th class="text-left p-2 w-1/4">Memo</th><th class="p-2 w-12"></th></tr></thead>
        <tbody id="jr-body">
          @for($i=0;$i<2;$i++)
          <tr>
            <td class="p-2"><select name="lines[{{ $i }}][account_id]" class="m-inp" required><option value="">Pilih akun</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select></td>
            <td class="p-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" class="m-inp text-right"></td>
            <td class="p-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" class="m-inp text-right"></td>
            <td class="p-2"><input type="text" name="lines[{{ $i }}][memo]" class="m-inp" placeholder="Memo"></td>
            <td class="p-2 text-center"><button type="button" class="m-btnp" onclick="this.closest('tr').remove(); calc()">×</button></td>
          </tr>
          @endfor
        </tbody>
        <tfoot><tr class="border-t border-slate-700"><td class="p-2 text-right font-semibold">TOTAL</td><td class="p-2 text-right" id="sum-debit">0.00</td><td class="p-2 text-right" id="sum-credit">0.00</td><td class="p-2" colspan="2"></td></tr></tfoot>
      </table>
    </div>
    <div class="flex gap-2 mt-3"><button type="button" class="m-btnp" id="add-row">+ Tambah Baris</button><button class="m-btn">Simpan</button></div>
  </form>
</div>
<script>
(function(){ const body=document.getElementById('jr-body'); const add=document.getElementById('add-row'); const sumD=document.getElementById('sum-debit'); const sumC=document.getElementById('sum-credit'); function calc(){ let d=0,c=0; body.querySelectorAll('input[name$="[debit]"]').forEach(i=>d+=parseFloat(i.value||0)); body.querySelectorAll('input[name$="[credit]"]').forEach(i=>c+=parseFloat(i.value||0)); sumD.textContent=d.toFixed(2); sumC.textContent=c.toFixed(2);} window.calc=calc; add.addEventListener('click',()=>{ const i=body.querySelectorAll('tr').length; const firstSel=body.querySelector('select'); let selHtml=''; if(firstSel){ selHtml=firstSel.outerHTML.replace(/\[0\]/g,'['+i+']'); } else { selHtml='<select name="lines['+i+'][account_id]" class="m-inp" required></select>'; } body.insertAdjacentHTML('beforeend',`<tr><td class=\"p-2\">${selHtml}</td><td class=\"p-2\"><input type=\"number\" step=\"0.01\" min=\"0\" name=\"lines[${i}][debit]\" class=\"m-inp text-right\"></td><td class=\"p-2\"><input type=\"number\" step=\"0.01\" min=\"0\" name=\"lines[${i}][credit]\" class=\"m-inp text-right\"></td><td class=\"p-2\"><input type=\"text\" name=\"lines[${i}][memo]\" class=\"m-inp\" placeholder=\"Memo\"></td><td class=\"p-2 text-center\"><button type=\"button\" class=\"m-btnp\" onclick=\"this.closest('tr').remove(); calc()\">×</button></td></tr>`); }); body.addEventListener('input',e=>{ if(e.target.matches('input')) calc(); }); calc(); })();
</script>
@endsection
BLADE

# --------------------------- SEEDERS -----------------------------------------
if [[ -d database/seeders ]]; then
  [[ -f database/seeders/AccountsTableSeeder.php ]] || write_file database/seeders/AccountsTableSeeder.php <<'PHP'
<?php
namespace Database\Seeders; use Illuminate\Database\Seeder; use App\Models\Account;
class AccountsTableSeeder extends Seeder{ public function run(): void{ $data=[['code'=>'1000','name'=>'Kas','type'=>1,'is_cash'=>true],['code'=>'1100','name'=>'Bank','type'=>1,'is_cash'=>true],['code'=>'1200','name'=>'Piutang Usaha','type'=>1],['code'=>'2000','name'=>'Hutang Usaha','type'=>2],['code'=>'3000','name'=>'Modal','type'=>3],['code'=>'4000','name'=>'Pendapatan','type'=>4],['code'=>'5000','name'=>'Beban Operasional','type'=>5]]; foreach($data as $d){ Account::firstOrCreate(['code'=>$d['code']], $d+['is_active'=>true]); } } }
PHP
  if [[ -f database/seeders/DatabaseSeeder.php ]] && ! grep -Fq 'AccountsTableSeeder::class' database/seeders/DatabaseSeeder.php; then
    append_once database/seeders/DatabaseSeeder.php ACCOUNTS_SEED_CALL <<'PHP'
        $this->call(\Database\Seeders\AccountsTableSeeder::class);
PHP
  fi
else
  ensure_dir database/seeds
  [[ -f database/seeds/AccountsTableSeeder.php ]] || write_file database/seeds/AccountsTableSeeder.php <<'PHP'
<?php
use Illuminate\Database\Seeder; use App\Models\Account;
class AccountsTableSeeder extends Seeder{ public function run(){ $data=[['code'=>'1000','name'=>'Kas','type'=>1,'is_cash'=>true],['code'=>'1100','name'=>'Bank','type'=>1,'is_cash'=>true],['code'=>'1200','name'=>'Piutang Usaha','type'=>1],['code'=>'2000','name'=>'Hutang Usaha','type'=>2],['code'=>'3000','name'=>'Modal','type'=>3],['code'=>'4000','name'=>'Pendapatan','type'=>4],['code'=>'5000','name'=>'Beban Operasional','type'=>5]]; foreach($data as $d){ Account::firstOrCreate(['code'=>$d['code']], $d+['is_active'=>true]); } } }
PHP
  if [[ -f database/seeds/DatabaseSeeder.php ]] && ! grep -Fq 'AccountsTableSeeder::class' database/seeds/DatabaseSeeder.php; then
    append_once database/seeds/DatabaseSeeder.php ACCOUNTS_SEED_CALL <<'PHP'
        $this->call(AccountsTableSeeder::class);
PHP
  fi
fi

# --------------------------- COMPOSER / MIGRATE ------------------------------
say "composer dump-autoload -o"
$DRYRUN || $COMPOSER_BIN dump-autoload -o || warn "composer gagal (lewati)"

if $RUN_MIGRATE; then ans=$(ask "Jalankan php artisan migrate sekarang?" "Y"); if [[ "$ans" =~ ^[Yy]$ ]] && ! $DRYRUN; then $PHP_BIN artisan migrate; fi; fi
if $RUN_SEED; then ans=$(ask "Jalankan seeder AccountsTableSeeder sekarang?" "Y"); if [[ "$ans" =~ ^[Yy]$ ]] && ! $DRYRUN; then if [[ -d database/seeds ]]; then $PHP_BIN artisan db:seed --class=AccountsTableSeeder; else $PHP_BIN artisan db:seed --class=Database\Seeders\AccountsTableSeeder; fi; fi; fi

say "Selesai. Anda dapat menjalankan ulang script ini kapan saja — aman & idempotent. ✨"
