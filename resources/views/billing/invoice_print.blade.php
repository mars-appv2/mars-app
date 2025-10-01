@extends('layouts.app')
@section('title','')

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

  $printedAt  = now()->format('d/m/Y H:i');
@endphp

<style>
@media print { @page { size: A4; margin: 0; } html, body { margin:0 !important; } }
aside, nav, header, footer, .sidebar, .sidenav, .topbar, .navbar { display:none !important; visibility:hidden !important; }
body { background:#0b1220; }

.toolbar { position:fixed; right:18px; top:18px; display:flex; gap:8px; z-index:50; }
@media print { .toolbar { display:none !important; } }

.print-stage { display:flex; justify-content:center; padding:24px; }
.paper { width:210mm; min-height:297mm; background:#fff; color:#000; box-shadow:0 8px 30px rgba(0,0,0,.25); border-radius:6px; padding:14mm; position:relative; }

.hdr { display:flex; align-items:center; gap:14px; }
.hdr .brand img { height:64px; width:64px; object-fit:contain; display:block; }
.hdr .name { font-size:20px; font-weight:700; letter-spacing:.3px; }
.hdr .sub { color:#555; font-size:12px; }

.title { margin:18px 0 8px; font-size:18px; font-weight:700; border-bottom:2px solid #e9eef5; padding-bottom:6px; }
.meta { width:100%; border-collapse:collapse; font-size:12px; color:#222; margin-top:6px; }
.meta td { padding:6px 0; vertical-align:top; }
.meta td.k { width:28%; color:#666; }
.sum { margin-top:14px; display:flex; justify-content:flex-end; }
.sum .box { border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; min-width:240px; }
.sum .row { display:flex; justify-content:space-between; margin:6px 0; font-size:12px; }
.sum .ttl { font-size:18px; font-weight:800; }
.footer { margin-top:18px; color:#333; font-size:12px; }
.printed-at { position:absolute; bottom:10mm; left:14mm; right:14mm; display:flex; justify-content:flex-end; color:#666; font-size:11px; }

@media print {
  .print-stage { padding:0 !important; }
  .paper { box-shadow:none !important; border-radius:0 !important; width:210mm; min-height:auto; }
}
</style>

<div class="toolbar no-print">
  <button class="m-btn m-btn-outline" type="button" onclick="goBack('{{ route('billing.invoices') }}')">Kembali</button>
  <button class="m-btn" onclick="window.print()">Print</button>
</div>

<div class="print-stage">
  <div class="paper">
    <div class="hdr">
      <div class="brand">@if($logoUrl)<img src="{{ $logoUrl }}" alt="" onerror="this.parentNode.innerHTML=''">@endif</div>
      <div>
        <div class="name">{{ $company }}</div>
        @if($address)<div class="sub">{{ $address }}</div>@endif
        @if($phone)  <div class="sub">Telp: {{ $phone }}</div>@endif
        @if($taxId)  <div class="sub">NPWP: {{ $taxId }}</div>@endif
      </div>
    </div>

    <div class="title">INVOICE</div>

    <table class="meta">
      <tr>
        <td class="k">No Invoice</td><td><b>{{ $row->number ?? ('INV-'.$row->id) }}</b></td>
        <td class="k">Periode</td><td><b>{{ $row->period ?? '—' }}</b></td>
      </tr>
      <tr>
        <td class="k">Customer</td><td><b>{{ $row->username ?? $row->customer_name ?? '—' }}</b></td>
        <td class="k">Jatuh Tempo</td><td><b>{{ \Illuminate\Support\Str::of($row->due_date)->replace(' 00:00:00','') }}</b></td>
      </tr>
      <tr>
        <td class="k">Subscription</td>
        <td><b>ID: {{ $row->subscription_id ?? '—' }}</b> <span style="color:#666;">({{ $row->subscription_name ?? '—' }})</span></td>
        <td class="k">Status</td><td><b>{{ strtoupper($row->status ?? 'unpaid') }}</b></td>
      </tr>
    </table>

    <div class="sum">
      <div class="box">
        <div class="row"><div>Subtotal</div><div>{{ number_format($subtotal,0,',','.') }}</div></div>
        <div class="row"><div>Diskon</div><div>{{ number_format($discount,0,',','.') }}</div></div>
        <div class="row"><div>PPN</div><div>{{ number_format($vat,0,',','.') }}</div></div>
        <hr style="border:0;border-top:1px solid #e5e7eb;margin:8px 0;">
        <div class="row ttl"><div>Total</div><div>{{ number_format($total,0,',','.') }}</div></div>
      </div>
    </div>

    <div class="footer">
      @if($note)<div>{{ $note }}</div>@endif
      @if($bankName || $bankNo)
        <div>Pembayaran: <b>{{ $bankName }}</b> {{ $bankNo }} a.n {{ $bankHolder }}</div>
      @endif
    </div>

    <div class="printed-at">Dicetak: {{ $printedAt }}</div>
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
