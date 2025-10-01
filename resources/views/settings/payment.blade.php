@extends('layouts.app')
@section('title','Pengaturan Pembayaran')

@section('content')
@if(session('ok'))
  <div class="m-card p-3 mb-4 text-green-300">{{ session('ok') }}</div>
@endif
@if(session('err'))
  <div class="m-card p-3 mb-4 text-red-300">{{ session('err') }}</div>
@endif

{{-- ========== PAYMENT GATEWAY (MIDTRANS) ========== --}}
<div class="m-card p-5 mb-5">
  <div class="text-lg text-slate-200 font-semibold mb-2">Payment Gateway (Midtrans)</div>
  <div class="text-xs text-[var(--muted)] mb-4">
    Aktifkan bila ingin terima pembayaran otomatis (VA BCA/BRI/Mandiri, GoPay, OVO, DANA). Isi Server Key & Client Key.
  </div>

  <form method="POST" action="{{ url('/settings/payment') }}" class="grid md:grid-cols-3 gap-4">
    @csrf
    <div class="md:col-span-2">
      <label class="m-lab">MIDTRANS_SERVER_KEY</label>
      <input name="server_key" class="m-inp" placeholder="SB-Mid-server-xxxxxxxx" value="{{ $server_key ?? '' }}">
    </div>
    <div>
      <label class="m-lab">MIDTRANS_CLIENT_KEY</label>
      <input name="client_key" class="m-inp" placeholder="SB-Mid-client-xxxxxxxx" value="{{ $client_key ?? '' }}">
    </div>
    <div class="flex items-center gap-2 md:col-span-3">
      <input id="is_production" type="checkbox" name="is_production" class="rounded"
             {{ !empty($is_production) ? 'checked' : '' }}>
      <label for="is_production" class="text-sm text-slate-300">Production Mode</label>
    </div>

    <div class="md:col-span-3 flex flex-wrap gap-3 items-center justify-between">
      <div class="text-xs text-[var(--muted)]">
        Webhook (Notification URL): <code>{{ url('/billing/payments/midtrans/notify') }}</code>
      </div>
      <button class="m-btn m-btnp">Simpan Gateway</button>
    </div>
  </form>
</div>

{{-- ========== REKENING BANK & E-WALLET ========== --}}
<div class="m-card p-5">
  <div class="text-lg text-slate-200 font-semibold mb-2">Rekening Bank & E-Wallet (Manual Transfer)</div>
  <div class="text-xs text-[var(--muted)] mb-4">
    Data ini muncul di halaman <em>Billing → Pembayaran</em> untuk pembayaran manual (konfirmasi oleh admin).
  </div>

  <form method="POST" action="{{ url('/settings/payment') }}" enctype="multipart/form-data" class="grid gap-4">
    @csrf

    <div class="grid md:grid-cols-2 gap-4">
      {{-- BCA --}}
      <div class="border border-[var(--line)] rounded p-4">
        <div class="font-semibold text-slate-200 mb-2">Bank BCA</div>
        <label class="m-lab">Nomor Rekening</label>
        <input name="bank_bca_no" class="m-inp" value="{{ $bank_bca_no ?? '' }}" placeholder="0123456789">
        <div class="mt-3"></div>
        <label class="m-lab">Atas Nama</label>
        <input name="bank_bca_name" class="m-inp" value="{{ $bank_bca_name ?? '' }}" placeholder="Nama Pemilik">
      </div>

      {{-- BRI --}}
      <div class="border border-[var(--line)] rounded p-4">
        <div class="font-semibold text-slate-200 mb-2">Bank BRI</div>
        <label class="m-lab">Nomor Rekening</label>
        <input name="bank_bri_no" class="m-inp" value="{{ $bank_bri_no ?? '' }}" placeholder="0123456789">
        <div class="mt-3"></div>
        <label class="m-lab">Atas Nama</label>
        <input name="bank_bri_name" class="m-inp" value="{{ $bank_bri_name ?? '' }}" placeholder="Nama Pemilik">
      </div>

      {{-- Mandiri --}}
      <div class="border border-[var(--line)] rounded p-4">
        <div class="font-semibold text-slate-200 mb-2">Bank Mandiri</div>
        <label class="m-lab">Nomor Rekening</label>
        <input name="bank_mandiri_no" class="m-inp" value="{{ $bank_mandiri_no ?? '' }}" placeholder="0123456789">
        <div class="mt-3"></div>
        <label class="m-lab">Atas Nama</label>
        <input name="bank_mandiri_name" class="m-inp" value="{{ $bank_mandiri_name ?? '' }}" placeholder="Nama Pemilik">
      </div>
    </div>

    <div class="pt-2 border-t border-[var(--line)]"></div>

    <div class="grid md:grid-cols-3 gap-4">
      {{-- GoPay --}}
      <div class="border border-[var(--line)] rounded p-4">
        <div class="font-semibold text-slate-200 mb-2">GoPay</div>
        <label class="m-lab">No. Akun / HP</label>
        <input name="ewallet_gopay_no" class="m-inp" value="{{ $ewallet_gopay_no ?? '' }}" placeholder="08xxxxxxxxxx">
        <div class="mt-3"></div>
        <label class="m-lab">Nama Akun</label>
        <input name="ewallet_gopay_name" class="m-inp" value="{{ $ewallet_gopay_name ?? '' }}" placeholder="Nama Akun">
        <div class="mt-3"></div>
        <label class="m-lab">QR (opsional)</label>
        <input type="file" name="gopay_qr" class="m-inp">
        @if(!empty($gopay_qr_url))
          <div class="mt-2 text-xs text-[var(--muted)]">Preview:</div>
          <img src="{{ $gopay_qr_url }}" alt="QR GoPay" class="mt-1 max-h-40 rounded border border-[var(--line)]">
        @endif
      </div>

      {{-- OVO --}}
      <div class="border border-[var(--line)] rounded p-4">
        <div class="font-semibold text-slate-200 mb-2">OVO</div>
        <label class="m-lab">No. Akun / HP</label>
        <input name="ewallet_ovo_no" class="m-inp" value="{{ $ewallet_ovo_no ?? '' }}" placeholder="08xxxxxxxxxx">
        <div class="mt-3"></div>
        <label class="m-lab">Nama Akun</label>
        <input name="ewallet_ovo_name" class="m-inp" value="{{ $ewallet_ovo_name ?? '' }}" placeholder="Nama Akun">
        <div class="mt-3"></div>
        <label class="m-lab">QR (opsional)</label>
        <input type="file" name="ovo_qr" class="m-inp">
        @if(!empty($ovo_qr_url))
          <div class="mt-2 text-xs text-[var(--muted)]">Preview:</div>
          <img src="{{ $ovo_qr_url }}" alt="QR OVO" class="mt-1 max-h-40 rounded border border-[var(--line)]">
        @endif
      </div>

      {{-- DANA --}}
      <div class="border border-[var(--line)] rounded p-4">
        <div class="font-semibold text-slate-200 mb-2">DANA</div>
        <label class="m-lab">No. Akun / HP</label>
        <input name="ewallet_dana_no" class="m-inp" value="{{ $ewallet_dana_no ?? '' }}" placeholder="08xxxxxxxxxx">
        <div class="mt-3"></div>
        <label class="m-lab">Nama Akun</label>
        <input name="ewallet_dana_name" class="m-inp" value="{{ $ewallet_dana_name ?? '' }}" placeholder="Nama Akun">
        <div class="mt-3"></div>
        <label class="m-lab">QR (opsional)</label>
        <input type="file" name="dana_qr" class="m-inp">
        @if(!empty($dana_qr_url))
          <div class="mt-2 text-xs text-[var(--muted)]">Preview:</div>
          <img src="{{ $dana_qr_url }}" alt="QR DANA" class="mt-1 max-h-40 rounded border border-[var(--line)]">
        @endif
      </div>
    </div>

    <div class="flex justify-end pt-2">
      <button class="m-btn m-btnp">Simpan Rekening & E-Wallet</button>
    </div>
  </form>
</div>
@endsection
