@extends('layouts.app')
@section('content')
<style>
.badge{font-size:.72rem;padding:.2rem .45rem;border-radius:.4rem;border:1px solid #2a3350;background:rgba(99,102,241,.15);color:#cbd5e1}
.m-card{background:#121827;border:1px solid #2a3350;border-radius:14px}
.m-head{color:#e6eaf2;font-weight:700}
.m-muted{color:#95a3bf}
.m-btn{background:#1e293b;color:#fff;border:1px solid #334155;border-radius:10px;padding:.55rem 1rem;transition:.15s}
.m-btn:hover{filter:brightness(1.12);transform:translateY(-1px)}
.m-btnp{background:#3b82f6;border-color:#3b82f6}
.m-inp{background:#0c101c;border:1px solid #2a3350;border-radius:10px;color:#e6eaf2;width:100%;padding:.55rem .75rem}
.m-lab{color:#95a3bf;font-size:.85rem;margin-bottom:.35rem;display:block}
.m-selwrap{position:relative}
.m-sel{appearance:none;background:#0c101c;border:1px solid #2a3350;border-radius:10px;color:#e6eaf2;padding:.55rem 2.2rem .55rem .75rem;width:100%}
.m-selwrap:after{content:'▾';position:absolute;right:.65rem;top:50%;transform:translateY(-50%);color:#95a3bf}
.m-table{width:100%;border-collapse:separate;border-spacing:0 8px}
.m-tr{background:#0f1526;border:1px solid #253052}
.m-th,.m-td{padding:.7rem .9rem;color:#e6eaf2}
.m-th{color:#95a3bf;font-weight:600;text-transform:uppercase;font-size:.75rem;letter-spacing:.04em}
.chip{padding:.2rem .55rem;border-radius:999px;border:1px solid #253052}
.chip-ok{background:#093b22;color:#22c55e;border-color:#14532d}
.chip-warn{background:#3b2b05;color:#f59e0b;border-color:#8a6b15}
.chip-err{background:#3b0b0b;color:#ef4444;border-color:#7f1d1d}
</style>

@php
  $secrets = is_iterable($secrets ?? []) ? collect($secrets) : collect([]);
  $active  = is_iterable($active ?? [])   ? collect($active) : collect([]);
  $activeNames = $active->map(function($r){ return is_array($r)?($r['name']??($r['user']??null)):null; })->filter()->values();
  $total = $secrets->count();
  $activeCount = $secrets->filter(function($s) use ($activeNames){ $nm=is_array($s)?($s['name']??null):null; return $nm && $activeNames->contains($nm);})->count();
  $inactiveCount = $total - $activeCount;
  $profileOptions = $secrets->pluck('profile')->filter()->unique()->values()->all();
  if (empty($profileOptions)) $profileOptions=['default'];
@endphp

<div class="container mx-auto px-2 md:px-4">
  <div class="mb-4 flex gap-2 items-center">
    <a href="{{ route('mikrotik.index') }}" class="m-btn">Table List</a>
    <a href="{{ route('mikrotik.dashboard',$mikrotik) }}" class="m-btn">Dashboard</a>
    <a href="{{ route('mikrotik.pppoe',$mikrotik) }}" class="m-btn m-btnp">PPPoE</a>
    <a href="{{ route('mikrotik.ipstatic',$mikrotik) }}" class="m-btn">IP Static</a>
    <span class="badge">hotfix-v5.2</span>
  </div>

  <div class="grid gap-4 md:grid-cols-4 mb-4">
    <div class="m-card p-4"><div class="m-muted text-sm">TOTAL USERS</div><div class="m-head" style="font-size:28px">{{ number_format($total) }}</div></div>
    <div class="m-card p-4"><div class="m-muted text-sm">ACTIVE USERS</div><div class="m-head" style="font-size:28px">{{ number_format($activeCount) }}</div></div>
    <div class="m-card p-4"><div class="m-muted text-sm">INACTIVE USERS</div><div class="m-head" style="font-size:28px">{{ number_format($inactiveCount) }}</div></div>
    <div class="m-card p-4"><div class="m-muted text-sm">ROUTER</div><div class="m-head" style="font-size:18px">{{ $mikrotik->name }} ({{ $mikrotik->host }}:{{ $mikrotik->port }})</div></div>
  </div>

  <div class="grid gap-5 lg:grid-cols-2 mb-6">
    <div class="m-card p-5">
      <div class="m-head mb-3">Tambah Client</div>
      <form method="POST" action="{{ route('mikrotik.pppoe.add',$mikrotik) }}">
        @csrf
        <label class="m-lab">Username</label><input name="name" class="m-inp" placeholder="mis: user01" required>
        <div style="height:10px"></div>
        <label class="m-lab">Password</label><input name="password" class="m-inp" placeholder="••••••••" required>
        <div style="height:10px"></div>
        <label class="m-lab">Profil</label>
        <div class="m-selwrap">
          <select name="profile" class="m-sel">
            @foreach($profileOptions as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
          </select>
        </div>
        <div class="flex items-center mt-3"><input type="checkbox" name="record" id="rec1" style="margin-right:.4rem"><label for="rec1" class="m-muted">Rekam trafik client ini</label></div>
        <div class="mt-4"><button class="m-btn m-btnp" type="submit">SIMPAN</button></div>
      </form>
    </div>

    <div class="m-card p-5">
      <div class="m-head mb-3">Tambah Profil</div>
      <form method="POST" action="{{ route('mikrotik.pppoe.profile.add',$mikrotik) }}">
        @csrf
        <label class="m-lab">Nama Profil</label><input name="profile" class="m-inp" placeholder="mis: 10M" required>
        <div style="height:10px"></div>
        <label class="m-lab">Rate-limit (up/down)</label><input name="rate" class="m-inp" placeholder="10M/10M" required>
        <div style="height:10px"></div>
        <label class="m-lab">Parent Queue (opsional)</label><input name="parent" class="m-inp" placeholder="global/default">
        <div class="mt-4"><button class="m-btn m-btnp" type="submit">SIMPAN</button></div>
      </form>
    </div>
  </div>

  <div class="m-card p-5">
    <div class="flex items-center justify-between mb-3">
      <div class="m-head">Kelola Client</div>
      <div style="min-width:280px">
        <label class="m-lab" for="pppSearch">Search</label>
        <input id="pppSearch" class="m-inp" placeholder="Cari username / profil / status ...">
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="m-table" id="pppTable">
        <thead>
          <tr>
            <th class="m-th">Username</th>
            <th class="m-th">Profil</th>
            <th class="m-th">Service</th>
            <th class="m-th">Status</th>
            <th class="m-th">Aksi</th>
          </tr>
        </thead>
        <tbody id="pppBody">
          @foreach($secrets as $s)
            @php
              $uname = $s['name'] ?? '';
              $prof  = $s['profile'] ?? 'default';
              $svc   = $s['service'] ?? 'pppoe';
              $disabled = ($s['disabled'] ?? 'false') === 'true';
              $isActive = $activeNames->contains($uname);
            @endphp
            <tr class="m-tr">
              <td class="m-td">{{ $uname }}</td>
              <td class="m-td">
                <form method="POST" action="{{ route('mikrotik.pppoe.edit',$mikrotik) }}" class="flex items-center" style="gap:.35rem">
                  @csrf
                  <input type="hidden" name="name" value="{{ $uname }}">
                  <div class="m-selwrap" style="min-width:160px">
                    <select name="profile" class="m-sel">
                      <option value="">—</option>
                      @foreach($profileOptions as $p)<option value="{{ $p }}" @if($p===$prof) selected @endif>{{ $p }}</option>@endforeach
                    </select>
                  </div>
                  <button class="m-btn" type="submit">Ubah</button>
                </form>
              </td>
              <td class="m-td">{{ $svc }}</td>
              <td class="m-td">
                @if($isActive)
                  <span class="chip chip-ok">active</span>
                @elseif($disabled)
                  <span class="chip chip-err">disabled</span>
                @else
                  <span class="chip chip-warn">enabled</span>
                @endif
              </td>
              <td class="m-td">
                <form method="POST" action="{{ route('mikrotik.pppoe.edit',$mikrotik) }}" style="display:inline">@csrf
                  <input type="hidden" name="name" value="{{ $uname }}">
                  @if($disabled)
                    <button class="m-btn" name="disabled" value="off" type="submit">Enable</button>
                  @else
                    <button class="m-btn" name="disabled" value="on" type="submit">Disable</button>
                  @endif
                </form>
                <form method="POST" action="{{ route('mikrotik.pppoe.record',$mikrotik) }}" style="display:inline">@csrf
                  <input type="hidden" name="name" value="{{ $uname }}">
                  <button class="m-btn" name="enable" value="toggle" type="submit">Rekam</button>
                </form>
                <form method="POST" action="{{ route('mikrotik.pppoe.delete',$mikrotik) }}" style="display:inline" onsubmit="return confirm('Hapus {{ $uname }}?')">@csrf
                  <input type="hidden" name="name" value="{{ $uname }}">
                  <button class="m-btn" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  const q=document.getElementById('pppSearch');
  const body=document.getElementById('pppBody');
  if(!q||!body) return;
  q.addEventListener('input', ()=> {
    const v=(q.value||'').toLowerCase();
    Array.from(body.querySelectorAll('tr')).forEach(tr=>{
      tr.style.display = tr.innerText.toLowerCase().includes(v) ? '' : 'none';
    });
  });
})();
</script>
@endsection
