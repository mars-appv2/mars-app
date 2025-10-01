@extends('layouts.app')
@section('title','WhatsApp Gateway')

@section('content')
@if(session('ok'))   <div class="m-card p-3 mb-4 text-green-300">{{ session('ok') }}</div> @endif
@if(session('err'))  <div class="m-card p-3 mb-4 text-red-300">{{ session('err') }}</div>  @endif

@php
  $waRefresh   = route('wa.refresh');
  $waQr        = route('wa.qr');
  $waSend      = route('wa.send');
  $waBroadcast = route('wa.broadcast');
  $waStatus    = route('wa.broadcast.status');
  $waCancel    = route('wa.broadcast.cancel');
@endphp

<div class="m-card p-5" x-data="waPage('{{ $waRefresh }}','{{ $waQr }}')">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <div class="text-lg font-semibold text-slate-200">WhatsApp Gateway</div>
      <div class="text-xs text-[var(--muted)]">
        Gateway: <code>{{ env('WA_GATEWAY_URL','http://127.0.0.1:3900') }}</code>
      </div>
    </div>
    <div class="flex items-center gap-4">
      <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" x-model="auto" class="m-chk">
        Auto refresh
      </label>
      <button class="m-btn m-btnp m-btn-icon" @click="load()">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M23 4v6h-6M1 20v-6h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M3.51 9a9 9 0 0115.38-3M20.49 15a9 9 0 01-15.38 3"/></svg>
        Refresh
      </button>
    </div>
  </div>

  <div class="grid md:grid-cols-3 gap-4 mt-4">
    <div class="md:col-span-1">
      <div class="p-4 rounded-xl border border-[var(--line)] bg-[var(--panel)]">
        <div class="text-sm text-[var(--muted)] mb-2">Status</div>
        <template x-if="error">
          <div class="text-red-300 text-sm" x-text="error"></div>
        </template>
        <div class="flex items-center gap-2">
          <span id="statusDot" class="w-2 h-2 rounded-full" :class="connected ? 'bg-green-400' : 'bg-red-400'"></span>
          <span class="text-slate-200" x-text="connected ? 'Connected' : 'Not connected'"></span>
        </div>
        <div class="mt-2 text-xs text-[var(--muted)] break-all">
          <template x-if="me">
            <div>Me: <span class="text-slate-200" x-text="JSON.stringify(me)"></span></div>
          </template>
          <template x-if="pairing">
            <div class="mt-1">Pairing code: <span class="text-slate-200" x-text="pairing"></span></div>
          </template>
        </div>
      </div>

      <div class="mt-4">
        <div class="text-sm text-[var(--muted)] mb-2">Scan QR</div>
        <template x-if="showQr">
          <img :src="qrUrl" class="rounded-lg border border-[var(--line)] w-full max-w-[260px]" alt="QR">
        </template>
        <template x-if="!showQr">
          <div class="text-xs text-[var(--muted)]">Tidak ada QR. Jika belum login, klik Refresh atau tunggu.</div>
        </template>
      </div>
    </div>

    <div class="md:col-span-2">
      <div class="p-4 rounded-xl border border-[var(--line)] bg-[var(--panel)]">
        <div class="text-sm text-[var(--muted)] mb-3">Kirim Pesan</div>
        <form method="POST" action="{{ $waSend }}" class="grid md:grid-cols-4 gap-3">
          @csrf
          <div class="md:col-span-1">
            <label class="m-lab">Nomor (628xxxx)</label>
            <input name="to" class="m-inp" placeholder="62812xxxxxxx" required>
          </div>
          <div class="md:col-span-3">
            <label class="m-lab">Pesan</label>
            <input name="text" class="m-inp" placeholder="Tulis pesan..." required>
          </div>
          <div class="md:col-span-4">
            <button class="m-btn m-btnp m-btn-icon">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
              Kirim
            </button>
          </div>
        </form>
      </div>

      <div class="p-4 mt-4 rounded-xl border border-[var(--line)] bg-[var(--panel)]">
        <div class="text-sm text-[var(--muted)] mb-3">Broadcast Sederhana (5 nomor/menit)</div>
        <form method="POST" action="{{ $waBroadcast }}" class="grid md:grid-cols-4 gap-3">
          @csrf
          <div class="md:col-span-2">
            <label class="m-lab">Nomor per baris</label>
            <textarea name="numbers" class="m-inp" rows="6" placeholder="62812xxxxxxx&#10;62813yyyyyyy"></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="m-lab">Pesan</label>
            <textarea name="text" class="m-inp" rows="6" placeholder="Isi pesan..."></textarea>
          </div>
          <div class="md:col-span-4">
            <button class="m-btn m-btnp m-btn-icon">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M3 12h18M3 19h18"/></svg>
              Kirim Broadcast
            </button>
          </div>
        </form>
      </div>

      {{-- STATUS JOB (tampil jika ada session wa_job) --}}
      @if(session('wa_job'))
        <div class="p-4 mt-4 rounded-xl border border-[var(--line)] bg-[var(--panel)]"
             x-data="waJob('{{ $waStatus }}','{{ $waCancel }}','{{ session('wa_job') }}')"
             x-init="loop()">
          <div class="flex items-center justify-between">
            <div class="text-sm text-[var(--muted)]">
              Status Broadcast — Job <span class="text-slate-200" x-text="id"></span>
            </div>
            <div class="text-xs text-[var(--muted)]">
              Laju: 5 nomor / menit
            </div>
          </div>

          <div class="text-slate-200 text-sm mt-1">
            Total: <span x-text="total"></span>
            • Terkirim: <span x-text="sent"></span>
            • Gagal: <span x-text="fail"></span>
            • Sisa: <span x-text="remaining"></span>
          </div>
          <div class="text-xs text-[var(--muted)] mt-1">
            <template x-if="last_error">
              <span class="text-red-300">Terakhir error: <span x-text="last_error"></span></span>
            </template>
            <span x-show="done && !cancelled" class="text-green-300 font-semibold ml-2">
              Broadcast selesai. Sukses: <span x-text="sent"></span>, Gagal: <span x-text="fail"></span>
            </span>
            <span x-show="cancelled" class="text-orange-300 font-semibold ml-2">
              Dibatalkan.
            </span>
          </div>

          <div class="mt-3 flex items-center gap-2">
            <button type="button" class="m-btn m-btn-ghost m-btn-icon" @click="load()">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M23 4v6h-6M1 20v-6h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M3.51 9a9 9 0 0115.38-3M20.49 15a9 9 0 01-15.38 3"/></svg>
              Refresh Status
            </button>

            <template x-if="!done">
              <button type="button" class="m-btn m-btn-ghost m-btn-icon" @click="cancelJob()">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancel Job
              </button>
            </template>

            <button type="button" class="m-btn m-btn-ghost m-btn-icon" @click="copySummary()">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 11h8M8 15h5"/><rect x="4" y="3" width="16" height="18" rx="2" ry="2"/></svg>
              Copy Ringkasan
            </button>
          </div>
        </div>
      @endif

    </div>
  </div>
</div>

<script>
function waPage(refreshUrl, qrBase){
  return {
    auto: true,
    connected: false,
    showQr: false,
    qrUrl: qrBase,
    me: null,
    pairing: null,
    error: null,
    t: null,
    load(){
      fetch(refreshUrl, {cache:'no-store'})
        .then(r => r.json())
        .then(j => {
          this.error     = j.error || null;
          this.connected = !!j.connected;
          this.me        = j.me || null;
          this.pairing   = j.pairing_code || null;
          this.showQr    = !!j.qr;
          this.qrUrl     = qrBase + '?t=' + Date.now();

          // status dot (fallback)
          const dot = document.querySelector('#statusDot');
          if (dot) dot.classList.toggle('bg-red-400', !this.connected);
          if (dot) dot.classList.toggle('bg-green-400', this.connected);
        })
        .catch(() => { this.error = 'Gateway tidak dapat diakses'; });
    },
    loop(){
      if (this.auto) this.load();
      this.t = setTimeout(() => this.loop(), 2000);
    },
    init(){
      this.load();
      this.loop();
    }
  }
}

function waJob(statusUrl, cancelUrl, id){
  return {
    id,
    total:0, sent:0, fail:0, remaining:0,
    done:false, cancelled:false, last_error:null,
    t:null,
    async load(){
      try{
        const r = await fetch(statusUrl + '?id=' + encodeURIComponent(this.id), {cache:'no-store'});
        const j = await r.json();
        if (j.ok) {
          this.total     = j.total || 0;
          this.sent      = j.sent || 0;
          this.fail      = j.fail || 0;
          this.remaining = j.remaining || 0;
          this.done      = !!j.done;
          this.cancelled = !!j.cancelled;
          this.last_error= j.last_error || null;
        }
      }catch(e){}
    },
    async loop(){
      await this.load();
      if(!this.done){ this.t = setTimeout(()=>this.loop(), 5000); }
      else { await this.load(); } // one final refresh to ensure final numbers
    },
    async cancelJob(){
      try{
        const form = new FormData();
        form.append('id', this.id);
        form.append('_token', '{{ csrf_token() }}');
        await fetch('{{ $waCancel }}', { method:'POST', body: form });
        await this.load();
      }catch(e){}
    },
    async copySummary(){
      const txt = `Job ${this.id}
Total: ${this.total}
Terkirim: ${this.sent}
Gagal: ${this.fail}
Sisa: ${this.remaining}
${this.cancelled ? 'DIBATALKAN' : (this.done ? 'SELESAI' : 'PROSES')}`;
      try{
        await navigator.clipboard.writeText(txt);
        alert('Ringkasan disalin.');
      }catch(e){
        alert('Gagal menyalin.');
      }
    }
  }
}
</script>
@endsection
