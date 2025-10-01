@extends('layouts.app')
@section('title','RADIUS Users')

@section('content')
@if(session('ok'))
  <div class="m-card p-3 mb-4 text-green-300">{{ session('ok') }}</div>
@endif
@if(session('err'))
  <div class="m-card p-3 mb-4 text-red-300">{{ session('err') }}</div>
@endif

<style>
  .tabs { display:flex; gap:.5rem; align-items:center; }
  .tab {
    padding:.45rem .85rem; border:1px solid var(--line); border-radius:.75rem;
    background:transparent; color:var(--muted); font-weight:600; font-size:.9rem;
  }
  .tab:hover{ background:rgba(255,255,255,.04); color:var(--text);}
  .tab.active{ background:var(--panel); color:var(--text); box-shadow:inset 0 0 0 1px rgba(127,156,255,.35);}
  .badge{ padding:.1rem .5rem; border-radius:999px; font-size:.75rem; border:1px solid transparent; }
  .badge-ok{ background:rgba(16,185,129,.1); color:rgb(110,231,183); border-color:rgba(16,185,129,.35);}
  .badge-bad{ background:rgba(244,63,94,.1); color:rgb(252,165,165); border-color:rgba(244,63,94,.35);}
</style>

<div x-data="{ tab: 'list' }" class="space-y-4">

  {{-- ===== Header + Filter ===== --}}
  <div class="m-card p-5">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div>
        <div class="text-lg font-semibold text-slate-200">RADIUS Users</div>
        <div class="text-xs text-[var(--muted)]">Kelola user RADIUS. Maksimal 10.000 hasil.</div>
      </div>

      <form method="GET" action="{{ route('radius.users') }}" class="grid grid-cols-1 md:grid-cols-3 gap-2 w-full md:w-auto">
        <div class="md:col-span-2">
          <label class="m-lab">Filter Perangkat</label>
          <select name="mikrotik_id" class="m-inp">
            <option value="">— semua perangkat aktif —</option>
            @foreach($devices as $d)
              <option value="{{ $d->id }}" {{ $sel==$d->id?'selected':'' }}>{{ $d->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="m-lab">Cari username</label>
          <div class="flex gap-2">
            <input name="q" value="{{ $q }}" class="m-inp" placeholder="cari username...">
            <button class="m-btn m-btnp m-btn-icon">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"/></svg>
              Filter
            </button>
          </div>
        </div>
      </form>
    </div>

    <div class="mt-4 tabs">
      <button class="tab" :class="{ 'active': tab==='list' }" @click="tab='list'">Daftar Users</button>
      <button class="tab" :class="{ 'active': tab==='add' }" @click="tab='add'">Tambah / Update</button>
      <button class="tab" :class="{ 'active': tab==='import' }" @click="tab='import'">Import</button>
    </div>
  </div>

  @include('radius._bulk_delete')

  {{-- ===== TAB LIST ===== --}}
  <div x-show="tab==='list'" x-cloak class="m-card p-5">
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-[var(--muted)]">
            <th class="py-2 pr-3">Username</th>
            <th class="py-2 pr-3">Plan</th>
            <th class="py-2 pr-3">Status</th>
            <th class="py-2 pr-3">Ubah Password</th>
            <th class="py-2">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @forelse($users as $u)
          @php
            $currentPlan = $groupMap[$u->username] ?? '';
            $st = $statusMap[$u->username] ?? 'active';
          @endphp
          <tr class="border-t border-[var(--line)]">
            <td class="py-2 pr-3 text-slate-200">{{ $u->username }}</td>

            {{-- PLAN --}}
            <td class="py-2 pr-3">
              <form method="POST" action="{{ route('radius.users.plan') }}" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="username" value="{{ $u->username }}">
                <select name="plan" class="m-inp">
                  <option value="">— tanpa plan —</option>
                  @foreach($plans as $p)
                    <option value="{{ $p }}" {{ $currentPlan===$p?'selected':'' }}>{{ $p }}</option>
                  @endforeach
                </select>
                <button class="m-btn m-btn-ghost m-btn-icon" title="Simpan plan">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h9l5 5v6z"/><path d="M7 7h6v4H7z"/></svg>
                </button>
              </form>
            </td>

            {{-- STATUS --}}
            <td class="py-2 pr-3">
              <span class="badge {{ $st==='active' ? 'badge-ok' : 'badge-bad' }}">
                {{ $st==='active' ? 'AKTIF' : 'INACTIVE' }}
              </span>
              <form method="POST" action="{{ route('radius.users.status') }}" class="inline">
                @csrf
                <input type="hidden" name="username" value="{{ $u->username }}">
                <input type="hidden" name="action" value="{{ $st==='active'?'deactivate':'activate' }}">
                <button class="m-btn m-btn-ghost m-btn-icon" title="{{ $st==='active'?'Nonaktifkan':'Aktifkan' }}">
                  @if($st==='active')
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v10m6.36-5.36a9 9 0 11-12.72 0"/></svg>
                  @else
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v10m-6.36-5.36a9 9 0 1012.72 0"/></svg>
                  @endif
                </button>
              </form>
            </td>

            {{-- PASSWORD --}}
            <td class="py-2 pr-3">
              <form method="POST" action="{{ route('radius.users.password') }}" class="flex gap-2">
                @csrf
                <input type="hidden" name="username" value="{{ $u->username }}">
                <input name="password" class="m-inp" placeholder="password baru">
                <button class="m-btn m-btnp m-btn-icon">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                  Update
                </button>
              </form>
            </td>

            {{-- HAPUS --}}
            <td class="py-2">
              <form method="POST" action="{{ route('radius.users.delete') }}" onsubmit="return confirm('Hapus {{ $u->username }}?')">
                @csrf
                <input type="hidden" name="username" value="{{ $u->username }}">
                <button class="m-btn m-btn-ghost m-btn-icon">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M7 7l1 12a2 2 0 002 2h4a2 2 0 002-2l1-12"/></svg>
                  Hapus
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-3 text-[var(--muted)]">Tidak ada data</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- ===== TAB ADD/UPDATE ===== --}}
  <div x-show="tab==='add'" x-cloak class="m-card p-5">
    <div class="text-slate-300 font-semibold mb-3">Tambah / Update User</div>
    <form method="POST" action="{{ route('radius.users.store') }}" class="grid md:grid-cols-4 gap-4">
      @csrf
      <div>
        <label class="m-lab">Username</label>
        <input name="username" class="m-inp" placeholder="mis. johndoe" required>
      </div>
      <div>
        <label class="m-lab">Password</label>
        <input name="password" type="password" class="m-inp" required>
      </div>
      <div>
        <label class="m-lab">Plan (PPP Profile)</label>
        <select name="plan" class="m-inp">
          <option value="">— tanpa plan —</option>
          @foreach($plans as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="m-lab">Perangkat (opsional)</label>
        <select name="mikrotik_id" class="m-inp">
          <option value="">— opsional —</option>
          @foreach($devices as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
        </select>
      </div>
      <div class="md:col-span-4">
        <button class="m-btn m-btnp m-btn-icon">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h9l5 5v6z"/><path d="M7 7h6v4H7z"/></svg>
          Simpan / Update
        </button>
      </div>
    </form>
  </div>

  {{-- ===== TAB IMPORT (OFFLINE + dari MIKROTIK) ===== --}}
  <div x-show="tab==='import'" x-cloak class="m-card p-5 space-y-6">

    {{-- === Import Offline CSV/TXT === --}}
    <div>
      <div class="text-slate-300 font-semibold mb-2">Import Offline (CSV / TXT)</div>
      <div class="text-xs text-[var(--muted)] mb-3">
        Aman: <b>tidak menyentuh Mikrotik</b>. Hanya tulis ke RADIUS (+ Subscriptions / Invoice sesuai pilihan).
      </div>
      <form method="POST" action="{{ route('radius.users.import.file') }}" enctype="multipart/form-data" class="grid gap-3 lg:grid-cols-12">
        @csrf
        <div class="lg:col-span-4">
          <label class="m-lab">File (.csv / .txt)</label>
          <input type="file" name="file" class="m-inp">
          <div class="text-xs text-[var(--muted)] mt-1">Atau tempel data di kotak teks.</div>
        </div>

        <div class="lg:col-span-3">
          <label class="m-lab">Mikrotik (opsional)</label>
          <select name="mikrotik_id" class="m-inp">
            <option value="">— none —</option>
            @foreach($devices as $d)
              <option value="{{ $d->id }}">{{ $d->name }} — {{ $d->host }}</option>
            @endforeach
          </select>
        </div>

        <div class="lg:col-span-2">
          <label class="m-lab">Buat Subscriptions</label>
          <select name="with_subs" class="m-inp">
            <option value="1">Ya</option>
            <option value="0">Tidak</option>
          </select>
        </div>

        <div class="lg:col-span-3">
          <label class="m-lab">Buat Invoice</label>
          <select name="with_invoice" class="m-inp">
            <option value="0">Tidak</option>
            <option value="1">Ya</option>
          </select>
        </div>

        <div class="lg:col-span-12">
          <label class="m-lab">Tempel data (satu baris per user)</label>
          <textarea name="payload" rows="4" class="m-inp" placeholder="username,password,plan,status,mikrotik_id
budi,rahasia,Silver,active,7
ani,pass123,Gold,inactive,7"></textarea>
          <div class="text-xs text-[var(--muted)] mt-1">
            Format baris: <code>user,password,plan,status,mik_id</code> atau <code>user:password:plan:status:mik_id</code>.
            Baris diawali <code>#</code>/<code>;</code> diabaikan.
          </div>
        </div>

        <div class="lg:col-span-12">
          <button class="m-btn m-btnp m-btn-icon">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Import CSV/TXT
          </button>
        </div>
      </form>
    </div>

    <div class="border-t border-[var(--line)]"></div>

    {{-- === Import dari Mikrotik (read-only) === --}}
    <div>
      <div class="text-slate-300 font-semibold mb-2">Import dari MikroTik</div>
      <form method="POST" action="{{ url('/radius/users/import') }}" class="grid md:grid-cols-3 gap-4">
        @csrf
        <div>
          <label class="m-lab">Pilih Perangkat</label>
          <select name="mikrotik_id" class="m-inp">
            <option value="">— semua perangkat milik saya —</option>
            @foreach($devices as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="m-lab">Plan Default (opsional)</label>
          <select name="plan_default" class="m-inp">
            <option value="">— tanpa plan —</option>
            @foreach($plans as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
          </select>
        </div>
        <div class="md:self-end">
          <button class="m-btn m-btnp m-btn-icon">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            Import
          </button>
        </div>
        <div class="md:col-span-3 text-xs text-[var(--muted)] leading-relaxed">
          * Password MikroTik tidak bisa dibaca. User baru diberi password random (atau <code>RADIUS_IMPORTED_DEFAULT_PASSWORD</code> pada .env). Maksimum 10.000 baris. Aksi ini <b>tidak mengubah</b> data di Mikrotik.
        </div>
      </form>
    </div>

  </div>

</div>
@endsection
