@extends('layouts.app')
@section('title','Billing — Invoices')

@section('content')
@php
  // env untuk prefilling form template
  $company    = env('INV_COMPANY', config('app.name'));
  $address    = env('INV_ADDRESS', '');
  $phone      = env('INV_PHONE', '');
  $taxId      = env('INV_TAX_ID', '');
  $logoUrl    = env('INV_LOGO_URL', '');

  $note       = env('INV_FOOTER_NOTE', 'Terima kasih atas kepercayaan Anda.');
  $bankName   = env('INV_BANK_NAME', '');
  $bankNo     = env('INV_BANK_ACC_NO', '');
  $bankHolder = env('INV_BANK_ACC_NAME', '');
@endphp

<div class="m-card p-5 mb-4">

  {{-- HEADER + TOOLBAR --}}
  <div class="flex items-center justify-between mb-4">
    <div class="text-lg text-slate-200 font-semibold">Invoices</div>
    <div class="flex gap-2">
      <button type="button" class="m-btn m-btn-outline" onclick="tgl('tplHeaderForm')">Edit Header</button>
      <button type="button" class="m-btn m-btn-outline" onclick="tgl('tplLogoForm')">Ganti Logo</button>
      <button type="button" class="m-btn m-btn-outline" onclick="tgl('tplFooterForm')">Edit Footer</button>
      <button type="button" class="m-btn m-btn-outline" onclick="history.back()">Kembali</button>
    </div>
  </div>

  {{-- flash --}}
  @if(session('ok'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-green-900/40 text-green-200 border border-green-800">
      {{ session('ok') }}
    </div>
  @endif
  @if(session('err'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-red-900/40 text-red-200 border border-red-800">
      {{ session('err') }}
    </div>
  @endif

  {{-- ==== INLINE FORMS (collapsed) ==== --}}
  <form id="tplHeaderForm" class="hidden mb-3 p-3 rounded border border-[var(--line)] bg-slate-800/40" method="POST" action="{{ route('billing.template.save') }}">
    @csrf
    <div class="grid md:grid-cols-4 gap-2">
      <input class="m-inp" type="text" name="inv_company" placeholder="Nama Perusahaan" value="{{ $company }}">
      <input class="m-inp" type="text" name="inv_address" placeholder="Alamat" value="{{ $address }}">
      <input class="m-inp" type="text" name="inv_phone"   placeholder="Telp" value="{{ $phone }}">
      <input class="m-inp" type="text" name="inv_tax_id"  placeholder="NPWP" value="{{ $taxId }}">
    </div>
    <div class="mt-2 flex justify-end"><button class="m-btn">Simpan Header</button></div>
  </form>

  <form id="tplLogoForm" class="hidden mb-3 p-3 rounded border border-[var(--line)] bg-slate-800/40" method="POST" action="{{ route('billing.template.save') }}" enctype="multipart/form-data">
    @csrf
    <div class="flex items-center gap-3">
      <input class="m-inp" type="file" name="logo" accept="image/*">
      @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="" style="height:40px" onerror="this.style.display='none'">
      @endif
      <button class="m-btn">Simpan Logo</button>
    </div>
  </form>

  <form id="tplFooterForm" class="hidden mb-3 p-3 rounded border border-[var(--line)] bg-slate-800/40" method="POST" action="{{ route('billing.template.save') }}">
    @csrf
    <textarea class="m-inp mb-2" name="footer_note" rows="2" placeholder="Catatan footer">{{ $note }}</textarea>
    <div class="grid md:grid-cols-3 gap-2">
      <input class="m-inp" type="text" name="bank_name"   placeholder="Nama Bank" value="{{ $bankName }}">
      <input class="m-inp" type="text" name="bank_no"     placeholder="No Rekening" value="{{ $bankNo }}">
      <input class="m-inp" type="text" name="bank_holder" placeholder="Atas Nama" value="{{ $bankHolder }}">
    </div>
    <div class="mt-2 flex justify-end"><button class="m-btn">Simpan Footer</button></div>
  </form>

  {{-- FILTER BAR --}}
  @php
    $mikrotikId = $mikrotikId ?? '';
    $status     = $status     ?? '';
    $from       = $from       ?? now()->format('Y-m');
    $to         = $to         ?? now()->format('Y-m');
    $q          = $q          ?? '';
  @endphp

  <form method="GET" class="grid md:grid-cols-5 gap-3 mb-4 items-end">
    <div class="md:col-span-1">
      <label class="m-lab">Filter Perangkat</label>
      <select name="mikrotik_id" class="m-inp">
        <option value="">— semua —</option>
        @foreach($devices as $d)
          <option value="{{ $d->id }}" {{ (string)$mikrotikId===(string)$d->id ? 'selected':'' }}>
            {{ $d->name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-1">
      <label class="m-lab">Status</label>
      <select name="status" class="m-inp">
        <option value="">— semua —</option>
        <option value="unpaid" {{ $status==='unpaid'?'selected':'' }}>unpaid</option>
        <option value="paid"   {{ $status==='paid'  ?'selected':'' }}>paid</option>
        <option value="void"   {{ $status==='void'  ?'selected':'' }}>void</option>
      </select>
    </div>

    <div class="md:col-span-1">
      <label class="m-lab">Dari (periode)</label>
      <input type="month" name="from" value="{{ $from }}" class="m-inp">
    </div>

    <div class="md:col-span-1">
      <label class="m-lab">Sampai (periode)</label>
      <input type="month" name="to" value="{{ $to }}" class="m-inp">
    </div>

    <div class="md:col-span-1">
      <label class="m-lab">Cari (No/Username)</label>
      <div class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}" class="m-inp" placeholder="INV.. / username..">
        <button class="m-btn">Terapkan</button>
      </div>
    </div>
  </form>

  {{-- peta perangkat: id -> object(...) --}}
  @php
    $deviceMap = collect($devices)->keyBy('id');
  @endphp

  {{-- BULK DELETE --}}
  <form method="POST" action="{{ route('billing.invoices.bulkDelete') }}"
        onsubmit="return confirm('Hapus invoice terpilih? Tindakan tidak bisa dibatalkan.');">
    @csrf

    <div class="flex items-center justify-between mb-2">
      <div class="text-[var(--muted)] text-sm">
        Centang beberapa invoice lalu klik <b>Hapus Terpilih</b>.
      </div>
      <div class="flex items-center gap-2">
        <label class="inline-flex items-center gap-2 text-sm text-[var(--muted)]">
          <input id="chk-all" type="checkbox" class="m-inp w-4 h-4">
          Pilih semua
        </label>
        <button type="submit" class="m-btn bg-red-600/80 hover:bg-red-600">Hapus Terpilih</button>
      </div>
    </div>

    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-[var(--muted)]">
            <th class="py-2 pr-3 w-10"></th>
            <th class="py-2 pr-3">ID</th>
            <th class="py-2 pr-3">No</th>
            <th class="py-2 pr-3">Subscription</th>
            <th class="py-2 pr-3">Username / Customer</th>
            <th class="py-2 pr-3">Mikrotik</th>
            <th class="py-2 pr-3">Periode</th>
            <th class="py-2 pr-3">Amount</th>
            <th class="py-2 pr-3">Status</th>
            <th class="py-2 pr-3">Jatuh Tempo</th>
            <th class="py-2">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @forelse($invoices as $row)
          @php
            $mkName = optional($deviceMap->get((int)($row->mikrotik_id ?? 0)))->name;
          @endphp
          <tr class="border-t border-[var(--line)]">
            <td class="py-2 pr-3">
              <input type="checkbox" name="ids[]" value="{{ $row->id }}" class="chk-row m-inp w-4 h-4">
            </td>
            <td class="py-2 pr-3">{{ $row->id }}</td>
            <td class="py-2 pr-3">{{ $row->number ?? '—' }}</td>
            <td class="py-2 pr-3">{{ $row->subscription_id }}</td>
            <td class="py-2 pr-3">{{ $row->customer_name ?? $row->username ?? '—' }}</td>
            <td class="py-2 pr-3">{{ $mkName ?? '—' }}</td>
            <td class="py-2 pr-3">{{ $row->period ?? '—' }}</td>
            <td class="py-2 pr-3">{{ number_format((int)($row->total ?? $row->amount ?? 0),0,',','.') }}</td>
            <td class="py-2 pr-3">
              <span class="px-2 py-0.5 rounded text-xs
                {{ $row->status==='paid' ? 'bg-green-900/40 text-green-200 border border-green-800'
                   : ($row->status==='void' ? 'bg-slate-700 text-slate-300'
                   : 'bg-yellow-900/40 text-yellow-200 border border-yellow-800') }}">
                {{ $row->status ?? 'unpaid' }}
              </span>
            </td>
            <td class="py-2 pr-3">
              {{ $row->due_date ? \Illuminate\Support\Str::of($row->due_date)->replace(' 00:00:00','') : '—' }}
            </td>
            <td class="py-2">
              <a class="m-btn m-btn-outline" href="{{ route('billing.invoices.show',$row->id) }}">Lihat</a>
              <a class="m-btn" href="{{ route('billing.invoices.print',$row->id) }}" target="_blank">Print</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="11" class="py-4 text-[var(--muted)]">Tidak ada data.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </form>
</div>

{{-- JS toggle form + pilih semua --}}
<script>
  function tgl(id){ const el=document.getElementById(id); if(!el) return; el.classList.toggle('hidden'); }
  const chkAll=document.getElementById('chk-all'); const rows=document.querySelectorAll('.chk-row');
  if(chkAll){ chkAll.addEventListener('change', ()=>{ rows.forEach(c=>c.checked=chkAll.checked); }); }
</script>
@endsection
