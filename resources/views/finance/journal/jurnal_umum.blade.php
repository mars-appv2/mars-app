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


  <div class="m-card p-4 mb-4">
    <form method="GET" action="{{ route('finance.journal.export.csv') }}" class="flex gap-2 items-end">
      <div><label class="text-xs text-slate-400">Dari</label><input type="date" name="start" class="m-inp"></div>
      <div><label class="text-xs text-slate-400">Sampai</label><input type="date" name="end" class="m-inp"></div>
      <button class="m-btnp">Export CSV</button>
      @if(Route::has('finance.journal.export.pdf'))
        <button formaction="{{ route('finance.journal.export.pdf') }}" class="m-btnp">Export PDF</button>
      @endif
    </form>
  </div>
</div>
<script>
(function(){ const body=document.getElementById('jr-body'); const add=document.getElementById('add-row'); const sumD=document.getElementById('sum-debit'); const sumC=document.getElementById('sum-credit'); function calc(){ let d=0,c=0; body.querySelectorAll('input[name$="[debit]"]').forEach(i=>d+=parseFloat(i.value||0)); body.querySelectorAll('input[name$="[credit]"]').forEach(i=>c+=parseFloat(i.value||0)); sumD.textContent=d.toFixed(2); sumC.textContent=c.toFixed(2);} window.calc=calc; add.addEventListener('click',()=>{ const i=body.querySelectorAll('tr').length; const firstSel=body.querySelector('select'); let selHtml=''; if(firstSel){ selHtml=firstSel.outerHTML.replace(/\[0\]/g,'['+i+']'); } else { selHtml='<select name="lines['+i+'][account_id]" class="m-inp" required></select>'; } body.insertAdjacentHTML('beforeend',`<tr><td class=\"p-2\">${selHtml}</td><td class=\"p-2\"><input type=\"number\" step=\"0.01\" min=\"0\" name=\"lines[${i}][debit]\" class=\"m-inp text-right\"></td><td class=\"p-2\"><input type=\"number\" step=\"0.01\" min=\"0\" name=\"lines[${i}][credit]\" class=\"m-inp text-right\"></td><td class=\"p-2\"><input type=\"text\" name=\"lines[${i}][memo]\" class=\"m-inp\" placeholder=\"Memo\"></td><td class=\"p-2 text-center\"><button type=\"button\" class=\"m-btnp\" onclick=\"this.closest('tr').remove(); calc()\">×</button></td></tr>`); }); body.addEventListener('input',e=>{ if(e.target.matches('input')) calc(); }); calc(); })();
</script>
@endsection
