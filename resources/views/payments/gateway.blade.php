@extends('layouts.app')
@section('title','Pembayaran — Gateway')

@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Payment Gateway</div>

  @if(session('ok'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-green-900/40 text-green-200 border border-green-800">
      {{ session('ok') }}
    </div>
  @endif

  <form method="POST" action="{{ route('payments.gateway.save') }}" class="grid lg:grid-cols-12 gap-4">
    @csrf

    <div class="lg:col-span-4">
      <label class="m-lab">Provider</label>
      <select name="provider" class="m-inp">
        <option value="none"     {{ $cfg['provider']==='none'?'selected':'' }}>— tidak pakai gateway —</option>
        <option value="midtrans" {{ $cfg['provider']==='midtrans'?'selected':'' }}>Midtrans (OVO/DANA/GoPay/Bank)</option>
        <option value="xendit"   {{ $cfg['provider']==='xendit'?'selected':'' }}>Xendit (E-Wallet & Bank)</option>
      </select>
    </div>

    {{-- MIDTRANS --}}
    <div class="lg:col-span-12 grid lg:grid-cols-12 gap-4 border border-[var(--line)] rounded p-4">
      <div class="lg:col-span-12 text-[var(--muted)] text-xs">Kredensial Midtrans</div>
      <div class="lg:col-span-5">
        <label class="m-lab">Server Key</label>
        <input name="mid_server_key" value="{{ $cfg['mid_server_key'] }}" class="m-inp" placeholder="SB-Mid-server-xxxxx">
      </div>
      <div class="lg:col-span-5">
        <label class="m-lab">Client Key</label>
        <input name="mid_client_key" value="{{ $cfg['mid_client_key'] }}" class="m-inp" placeholder="SB-Mid-client-xxxxx">
      </div>
      <div class="lg:col-span-2">
        <label class="m-lab">Mode</label>
        <label class="inline-flex items-center gap-2 mt-2">
          <input type="checkbox" name="mid_is_production" value="1" class="accent-slate-300" {{ $cfg['mid_is_production']=='1'?'checked':'' }}>
          Production
        </label>
      </div>
    </div>

    {{-- XENDIT --}}
    <div class="lg:col-span-12 grid lg:grid-cols-12 gap-4 border border-[var(--line)] rounded p-4">
      <div class="lg:col-span-12 text-[var(--muted)] text-xs">Kredensial Xendit</div>
      <div class="lg:col-span-6">
        <label class="m-lab">Secret API Key</label>
        <input name="xendit_key" value="{{ $cfg['xendit_key'] }}" class="m-inp" placeholder="xnd_development_xxx">
      </div>
      <div class="lg:col-span-6 flex items-end">
        <div class="text-xs text-[var(--muted)]">
          Gunakan salah satu provider saja. Integrasi charge/notification belum diaktifkan pada build ini.
        </div>
      </div>
    </div>

    <div class="lg:col-span-12">
      <button class="m-btn">Simpan</button>
    </div>
  </form>
</div>
@endsection
