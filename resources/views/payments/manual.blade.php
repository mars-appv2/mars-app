@extends('layouts.app')
@section('title','Pembayaran — Manual')

@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Pembayaran Manual</div>

  @if(session('ok'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-green-900/40 text-green-200 border border-green-800">
      {{ session('ok') }}
    </div>
  @endif

  <form method="POST" action="{{ route('payments.manual.save') }}" enctype="multipart/form-data" class="grid lg:grid-cols-12 gap-4">
    @csrf

    <div class="lg:col-span-12">
      <label class="m-lab">Aktifkan pembayaran manual</label>
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="enable_manual" value="1" class="accent-slate-300" {{ $cfg['enable_manual']=='1'?'checked':'' }}>
        <span class="text-sm text-[var(--muted)]">Transfer bank / QRIS / e-wallet tanpa gateway</span>
      </label>
    </div>

    <div class="lg:col-span-4">
      <div class="text-[var(--muted)] text-xs mb-2">Bank Mandiri</div>
      <input name="mandiri_name" value="{{ $cfg['mandiri_name'] }}" class="m-inp mb-2" placeholder="Nama pemilik">
      <input name="mandiri_no"   value="{{ $cfg['mandiri_no']   }}" class="m-inp"       placeholder="No. rekening">
    </div>
    <div class="lg:col-span-4">
      <div class="text-[var(--muted)] text-xs mb-2">BCA</div>
      <input name="bca_name" value="{{ $cfg['bca_name'] }}" class="m-inp mb-2" placeholder="Nama pemilik">
      <input name="bca_no"   value="{{ $cfg['bca_no']   }}" class="m-inp"       placeholder="No. rekening">
    </div>
    <div class="lg:col-span-4">
      <div class="text-[var(--muted)] text-xs mb-2">BRI</div>
      <input name="bri_name" value="{{ $cfg['bri_name'] }}" class="m-inp mb-2" placeholder="Nama pemilik">
      <input name="bri_no"   value="{{ $cfg['bri_no']   }}" class="m-inp"       placeholder="No. rekening">
    </div>

    <div class="lg:col-span-6">
      <label class="m-lab">QRIS (gambar PNG)</label>
      <input type="file" name="qris" accept="image/*" class="m-inp">
      @if($cfg['qris_url'])
        <div class="mt-2 text-xs text-[var(--muted)]">Saat ini:</div>
        <img src="{{ $cfg['qris_url'] }}" alt="QRIS" class="mt-1 w-40 h-40 border border-[var(--line)] rounded">
      @endif
    </div>

    <div class="lg:col-span-6">
      <label class="m-lab">E-Wallet (manual via QR)</label>
      <div class="flex flex-col gap-2">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="enable_ovo" class="accent-slate-300" value="1" {{ $cfg['enable_ovo']=='1'?'checked':'' }}> OVO</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="enable_dana" class="accent-slate-300" value="1" {{ $cfg['enable_dana']=='1'?'checked':'' }}> DANA</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="enable_gopay" class="accent-slate-300" value="1" {{ $cfg['enable_gopay']=='1'?'checked':'' }}> GoPay</label>
        <div class="text-xs text-[var(--muted)]">Catatan: pembayaran ini tidak otomatis — admin perlu verifikasi manual.</div>
      </div>
    </div>

    <div class="lg:col-span-12">
      <button class="m-btn">Simpan</button>
    </div>
  </form>
</div>
@endsection
