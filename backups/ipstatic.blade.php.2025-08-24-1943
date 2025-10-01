@extends('layouts.app')
@section('content')
@php $ifs = $if ?? []; @endphp
<style>/* ipstatic mini theme */
.badge{font-size:.72rem;padding:.2rem .45rem;border-radius:.4rem;border:1px solid #2a3350;background:rgba(99,102,241,.15);color:#cbd5e1}
.m-card{background:#121827;border:1px solid #2a3350;border-radius:14px}
.m-btn{background:#1e293b;color:#fff;border:1px solid #334155;border-radius:10px;padding:.55rem 1rem;transition:.15s}
.m-btn:hover{filter:brightness(1.12);transform:translateY(-1px)}
.m-btnp{background:#3b82f6;border-color:#3b82f6}
.m-inp,.m-sel{background:#0c101c;border:1px solid #2a3350;border-radius:10px;color:#e6eaf2;padding:.55rem .75rem}
.m-sel{padding-right:2.2rem;appearance:none}
.m-selwrap{position:relative}
.m-selwrap:after{content:"▾";position:absolute;right:.65rem;top:50%;transform:translateY(-50%);color:#95a3bf}
.small{font-size:.85rem;color:#95a3bf}
</style>
@extends('layouts.app')
@section('content')
<div class="container mx-auto px-2 md:px-4">
  <div class="mb-4 flex gap-2 items-center">
    <a href="{{ route('mikrotik.index') }}" class="m-btn">Table List</a>
    <a href="{{ route('mikrotik.dashboard',$mikrotik) }}" class="m-btn">Dashboard</a>
    <a href="{{ route('mikrotik.pppoe',$mikrotik) }}" class="m-btn">PPPoE</a>
    <a href="{{ route('mikrotik.ipstatic',$mikrotik) }}" class="m-btn m-btnp">IP Static</a>
  </div>

  @php
    // Pastikan $if ada (fallback aman)
    $if = $if ?? [];
    $ifaceOptions = [];
    foreach ($if as $row) {
      $nm = is_array($row) ? ($row['name'] ?? '') : (is_object($row) ? ($row->name ?? '') : (is_string($row)? $row : ''));
      if ($nm) { $ifaceOptions[] = $nm; }
    }
    sort($ifaceOptions);
  @endphp

  <div class="m-card">
    <div class="p-4 space-y-4">
      <h3 class="text-lg font-semibold" style="color:#cfe3ff;">Tambah IP Address</h3>
      <form method="POST" action="{{ route('mikrotik.ipstatic.add',$mikrotik) }}" class="grid md:grid-cols-3 gap-3">
        @csrf
        <div>
          <label class="small mb-1 block">Address (CIDR)</label>
          <input name="ip" class="m-inp w-full" placeholder="contoh: 192.168.10.2/24" required>
        </div>
        <div>
          <label class="small mb-1 block">Interface</label>
          <div class="m-selwrap">
            <select name="iface" class="m-sel w-full" required>
        @if(empty($ifs))
          <div id="mk-iface-empty-hint" class="small mt-2" style="color:#fca5a5">Tidak bisa memuat daftar interface. Cek koneksi API Mikrotik.</div>
        @endif
              <option value="" disabled selected>— pilih interface —</option>
              @foreach($ifaceOptions as $nm)
                <option value="{{ $nm }}">{{ $nm }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div>
          <label class="small mb-1 block">Comment (opsional)</label>
          <input name="comment" class="m-inp w-full" placeholder="keterangan">
        </div>
        <div class="md:col-span-3">
          <button class="m-btn m-btnp" type="submit">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <div class="m-card mt-4">
    <div class="p-4 space-y-4">
      <h3 class="text-lg font-semibold" style="color:#cfe3ff;">Hapus IP Address</h3>
      <form method="POST" action="{{ route('mikrotik.ipstatic.remove',$mikrotik) }}" class="grid md:grid-cols-2 gap-3">
        @csrf
        <div>
          <label class="small mb-1 block">Address (CIDR) yang dihapus</label>
          <input name="ip" class="m-inp w-full" placeholder="contoh: 192.168.10.2/24" required>
        </div>
        <div class="md:col-span-2">
          <button class="m-btn" type="submit">Hapus</button>
        </div>
      </form>
    </div>
  </div>

  @if(session('ok'))
    <div class="mt-3" style="color:#86efac">{{ session('ok') }}</div>
  @endif
  @if(session('err'))
    <div class="mt-3" style="color:#fca5a5">{{ session('err') }}</div>
  @endif
  @if($errors->any())
    <div class="mt-3" style="color:#fca5a5">
      @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
  @endif
</div>
@endsection
