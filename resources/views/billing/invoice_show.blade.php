@extends('layouts.app')
@section('title','Billing — Invoice')

@section('content')
@php
  $company = env('INV_COMPANY', config('app.name'));
  $address = env('INV_ADDRESS', '');
  $phone   = env('INV_PHONE', '');
  $taxId   = env('INV_TAX_ID', '');
  $logoUrl = env('INV_LOGO_URL', '');

  $note        = env('INV_FOOTER_NOTE', 'Terima kasih atas kepercayaan Anda.');
  $bankName    = env('INV_BANK_NAME', '');
  $bankNo      = env('INV_BANK_ACC_NO', '');
  $bankHolder  = env('INV_BANK_ACC_NAME', '');

  $subtotal   = (int)($row->amount ?? 0);
  $basePrice  = (int)($row->base_price ?? 0);
  if ($subtotal === 0 && $basePrice > 0) $subtotal = $basePrice;
  if ($subtotal === 0 && (int)($row->total ?? 0) > 0) {
    $subtotal = max(0, (int)$row->total - (int)($row->vat ?? 0) + (int)($row->discount ?? 0));
  }
  $discount   = (int)($row->discount ?? 0);
  $vatPercent = is_numeric($row->vat_percent ?? null) ? (int)$row->vat_percent : (int)env('BILLING_VAT_PERCENT',0);
  $vatField   = isset($row->vat) ? (int)$row->vat : null;
  $vatCalc    = max(0, (int) floor(max(0, $subtotal - $discount) * $vatPercent / 100));
  $vat        = $vatField !== null ? $vatField : $vatCalc;
  $total      = (int)($row->total ?? max(0, $subtotal - $discount + $vat));
@endphp

<style>
.inv-grid td.k { width: 28%; color: var(--muted); }
.inv-grid td.v { font-weight: 600; }
.inv-sum { display:flex; justify-content:flex-end; margin-top:14px; }
.inv-sum .box { border:1px solid var(--line); border-radius:10px; padding:14px 16px; min-width:280px; background:#0b1220; }
.inv-sum .row { display:flex; justify-content:space-between; margin:6px 0; font-size:14px; }
.inv-sum .ttl { font-size:20px; font-weight:800; }
.toolbar { display:flex; gap:8px; margin-bottom:12px; }
#logoForm, #footerForm { display:none; }
#logoForm.show, #footerForm.show { display:flex; gap:8px; align-items:center; margin-bottom:10px; }
#footerForm .col { display:flex; gap:8px; }
#footerForm .col .m-inp, #footerForm textarea { width:100%; }
.footer-note { margin-top:18px; color:var(--muted); font-size:12px; }
.inv-hdr { display:flex; align-items:center; gap:14px; }
.inv-hdr .brand img { height:64px; width:64px; object-fit:contain; display:block; }
.inv-hdr .company .name { font-size:20px; font-weight:700; }
.inv-hdr .company .sub  { font-size:12px; color:var(--muted); }
</style>

<div class="toolbar no-print">
  <a href="{{ route('billing.template.edit') }}" class="m-btn m-btn-outline">Edit Header</a>
  <button type="button" class="m-btn m-btn-outline" onclick="document.getElementById('logoForm').classList.toggle('show')">Ganti Logo</button>
  <button type="button" class="m-btn m-btn-outline" onclick="document.getElementById('footerForm').classList.toggle('show')">Edit Footer</button>
  <a class="m-btn" href="{{ route('billing.invoices.print',$row->id) }}" target="_blank">Print</a>
  <button type="button" class="m-btn m-btn-outline" onclick="goBack('{{ route('billing.invoices') }}')">Kembali</button>
</div>

<form id="logoForm" class="no-print" method="POST" action="{{ route('billing.template.save') }}" enctype="multipart/form-data">
  @csrf
  <input type="file" name="logo" accept="image/*" class="m-inp">
  <button class="m-btn">Simpan Logo</button>
</form>

<form id="footerForm" class="no-print flex flex-col gap-2 w-full" method="POST" action="{{ route('billing.template.save') }}">
  @csrf
  <textarea name="footer_note" rows="2" class="m-inp" placeholder="Catatan footer (mis. terima kasih/petunjuk pembayaran)">{{ $note }}</textarea>
  <div class="col w-full">
    <input  class="m-inp" type="text" name="bank_name"   placeholder="Nama Bank"      value="{{ $bankName }}">
    <input  class="m-inp" type="text" name="bank_no"     placeholder="No. Rekening"   value="{{ $bankNo }}">
    <input  class="m-inp" type="text" name="bank_holder" placeholder="Atas Nama"      value="{{ $bankHolder }}">
    <button class="m-btn">Simpan Footer</button>
  </div>
</form>

<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-6">Invoice #{{ $row->number ?? ('INV-'.$row->id) }}</div>

  <div class="inv-hdr mb-6">
    <div class="brand">
      @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="" onerror="this.parentNode.innerHTML=''">
      @endif
    </div>
    <div class="company">
      <div class="name">{{ $company }}</div>
      @if($address)<div class="sub">{{ $address }}</div>@endif
      @if($phone)<div class="sub">Telp: {{ $phone }}</div>@endif
      @if($taxId)<div class="sub">NPWP: {{ $taxId }}</div>@endif
    </div>
  </div>

  <table class="w-full inv-grid text-sm">
    <tr>
      <td class="k py-1">No Invoice</td><td class="v py-1">{{ $row->number ?? ('INV-'.$row->id) }}</td>
      <td class="k py-1">Periode</td><td class="v py-1">{{ $row->period ?? '—' }}</td>
    </tr>
    <tr>
      <td class="k py-1">Customer</td><td class="v py-1">{{ $row->username ?? $row->customer_name ?? '—' }}</td>
      <td class="k py-1">Jatuh Tempo</td><td class="v py-1">{{ \Illuminate\Support\Str::of($row->due_date)->replace(' 00:00:00','') }}</td>
    </tr>
    <tr>
      <td class="k py-1">Subscription</td>
      <td class="v py-1">ID: {{ $row->subscription_id ?? '—' }} <span class="text-[var(--muted)]">({{ $row->subscription_name ?? '—' }})</span></td>
      <td class="k py-1">Status</td>
      <td class="py-1">
        <span class="px-2 py-0.5 rounded text-xs
          {{ ($row->status ?? '')==='paid' ? 'bg-green-900/40 text-green-200 border border-green-800'
             : (($row->status ?? '')==='void' ? 'bg-slate-700 text-slate-300'
             : 'bg-yellow-900/40 text-yellow-200 border border-yellow-800') }}">
          {{ strtoupper($row->status ?? 'unpaid') }}
        </span>
      </td>
    </tr>
  </table>

  <div class="inv-sum">
    <div class="box">
      <div class="row"><div>Subtotal</div><div>{{ number_format($subtotal,0,',','.') }}</div></div>
      <div class="row"><div>Diskon</div><div>{{ number_format($discount,0,',','.') }}</div></div>
      <div class="row"><div>PPN</div><div>{{ number_format($vat,0,',','.') }}</div></div>
      <hr class="my-2 border-[var(--line)]">
      <div class="row ttl"><div>Total</div><div>{{ number_format($total,0,',','.') }}</div></div>
    </div>
  </div>

  <div class="footer-note">
    @if($note)<div>{{ $note }}</div>@endif
    @if($bankName || $bankNo)
      <div>Pembayaran: <b>{{ $bankName }}</b> {{ $bankNo }} a.n {{ $bankHolder }}</div>
    @endif
  </div>
</div>

<script>
  function goBack(fallbackUrl){
    try {
      if (document.referrer) {
        const ref = new URL(document.referrer);
        if (ref.host === location.host) { history.back(); return; }
      }
    } catch(e) { /* ignore */ }
    window.location.href = fallbackUrl;
  }
</script>
@endsection

