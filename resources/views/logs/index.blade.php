@extends('layouts.app')

@section('content')
<div id="audit-logs" class="container mx-auto px-3 py-3"><!-- SCOPE WRAPPER -->

  <style>
    #audit-logs{
      --panel:#111a2d; --panel2:#0f172a; --head:#0f1a31;
      --stroke:rgba(122,151,255,.18);
      --fg:#eaf0ff; --fg2:#c6d0f5; --muted:#98a4c8;
      --ok:#19c37d; --warn:#f6c453; --err:#ff6b6b;
      --p1:#2f66ff; --p2:#214ccc;
    }
    #audit-logs{ color:var(--fg); }

    /* Card */
    .m-card{
      background: var(--panel);
      border-radius: 16px;
      border: 1px solid var(--stroke);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.02), 0 12px 32px rgba(0,0,0,.35), 0 2px 0 rgba(122,151,255,.06);
      overflow: visible;
    }

    /* Header line: actions nempel judul, tidak mepet kanan */
    .header-line{
      display:flex; align-items:center; gap:10px; flex-wrap:wrap;
      justify-content:flex-start;
    }
    .header-actions{ display:flex; gap:8px; }

    /* Buttons: kecil */
    .m-btn, .m-btnp, #audit-logs button[type="submit"]{
      height: 34px; border-radius: 10px;
      padding: 0 12px; font-weight: 700; letter-spacing: .4px; font-size:.9rem; color: #fff;
      background: linear-gradient(180deg, var(--p1), var(--p2));
      border: 1px solid rgba(117,160,255,.45);
      box-shadow: 0 8px 18px rgba(46,95,255,.18), inset 0 0 0 1px rgba(255,255,255,.05);
      white-space:nowrap;
    }
    .m-btn:hover{ filter: brightness(1.06); }

    /* Inputs / Select: lebih kecil, tidak menimbulkan overflow */
    .m-inp,.m-sel,
    #audit-logs input[type="text"],#audit-logs input[type="date"],#audit-logs select{
      background: var(--panel2) !important;
      color: var(--fg) !important;
      border: 1px solid rgba(122,151,255,.28) !important;
      border-radius: 10px !important;
      height: 38px; padding: 8px 12px !important;
      font-size:.95rem;
      box-shadow: none !important; outline: none !important;
      min-width: 160px; /* cegah terlalu kecil tapi tetap fleksibel */
    }
    .m-inp::placeholder{ color: var(--muted) !important; }
    .m-inp:focus,.m-sel:focus,#audit-logs input:focus,#audit-logs select:focus{
      border-color: rgba(117,160,255,.75) !important;
      box-shadow: 0 0 0 3px rgba(117,160,255,.18) !important;
    }

    /* Grid filter: auto-fit supaya pecah baris otomatis, tanpa melar halaman */
    .filters-grid{
      display:grid; gap:12px;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    /* DATE FIX: pakai ikon native, no pseudo/theme */
    .date-fix{ position: relative; }
    .date-fix input[type="date"]{
      background-image: none !important;
      -webkit-appearance: none; appearance: none;
      padding-right: 36px !important;
      box-shadow: none !important;
      --tw-ring-shadow: 0 0 #0000 !important;
      --tw-ring-offset-shadow: 0 0 #0000 !important;
    }
    .date-fix input[type="date"]::-webkit-calendar-picker-indicator{
      filter: invert(85%); opacity: .9; cursor: pointer; position: static;
    }
    .date-fix input[type="date"]::before,
    .date-fix input[type="date"]::after{ content:none!important; display:none!important; }

    /* Table */
    .tbl-wrap{ overflow-x:auto; border-radius:16px; border:1px solid var(--stroke); }
    table{ width:100%; border-collapse: separate; border-spacing:0; }
    thead th{
      background: var(--head) !important; color: var(--fg2) !important; font-weight:600 !important;
      padding:12px 14px !important; border-bottom:1px solid rgba(122,151,255,.22) !important; white-space:nowrap;
    }
    tbody td{
      color: var(--fg) !important; border-bottom:1px solid rgba(122,151,255,.18) !important;
      padding:12px 14px !important; vertical-align: top !important;
    }
    tbody tr:hover{ background: rgba(255,255,255,.03) !important; }
    .subtxt{ color: var(--muted); font-size:.82rem; margin-top:2px; }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .badge{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:.75rem; font-weight:700; letter-spacing:.3px; }

    /* Responsif ekstra: kalau layar sempit, actions turun & tetap rapi */
    @media (max-width: 900px){
      .header-actions{ width:100%; }
    }
  </style>

  @php
    // Dedup & bersihkan list user utk dropdown
    $uniqueUsers = collect($users ?? [])
      ->filter(fn($u) => !empty($u->user_id) && (!empty($u->user_name) || !empty($u->user_email)))
      ->unique('user_id')->values();
  @endphp

  {{-- HEADER + ACTIONS (menempel ke judul, tidak overflow) --}}
  <div class="m-card p-5 mb-4">
    <div class="header-line">
      <h3 class="text-lg font-semibold" style="color:var(--fg2)">Audit Log</h3>
      <div class="header-actions">
        <a href="{{ url()->current() }}" class="m-btn">Refresh</a>
        <button type="submit" form="filter-form" class="m-btn m-btnp">Filter</button>
      </div>
    </div>

    {{-- FILTERS (kolom lebih kecil, auto-fit, no horizontal overflow) --}}
    <form id="filter-form" method="GET" class="mt-4 filters-grid">
      <input type="text" name="q" value="{{ $q }}" class="m-inp" placeholder="Cari user/action/target">

      <select name="status" class="m-sel">
        <option value="">— status —</option>
        <option value="ok"    {{ $status==='ok'    ? 'selected':'' }}>ok</option>
        <option value="error" {{ $status==='error' ? 'selected':'' }}>error</option>
      </select>

      <select name="user_id" class="m-sel">
        <option value="">— user —</option>
        @foreach($uniqueUsers as $u)
          <option value="{{ $u->user_id }}" {{ (string)$userId===(string)$u->user_id ? 'selected':'' }}>
            {{ trim(($u->user_name ?? '').' '.(($u->user_email ?? '') ? '<'.$u->user_email.'>' : '')) }}
          </option>
        @endforeach
      </select>

      <div class="date-fix">
        <input type="date" name="from" value="{{ $from }}" class="m-inp">
      </div>
      <div class="date-fix">
        <input type="date" name="to" value="{{ $to }}" class="m-inp">
      </div>
    </form>
  </div>

  {{-- TABEL --}}
  <div class="m-card p-0 tbl-wrap">
    <table class="min-w-full text-sm">
      <thead>
        <tr>
          <th class="text-left">Waktu</th>
          <th class="text-left">User</th>
          <th class="text-left">Action</th>
          <th class="text-left">Target</th>
          <th class="text-left">Status</th>
          <th class="text-left">IP</th>
          <th class="text-left">Route</th>
          <th class="text-left">Info</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $row)
          @php
            $dt = $row->created_at instanceof \Illuminate\Support\Carbon
                  ? $row->created_at
                  : \Illuminate\Support\Carbon::parse($row->created_at);

            $action = strtoupper($row->action ?? '—');
            $method = strtoupper($row->method ?? '');
            $showMethod = $method && $method !== $action;

            $route = trim((string)($row->route ?? ''));
            $path  = trim((string)($row->path  ?? ''));
            $showPath = $path && ($path !== $route);
          @endphp

          <tr>
            <td>
              {{ $dt->format('Y-m-d') }}
              <div class="subtxt">{{ $dt->format('H:i:s') }}</div>
            </td>
            <td>
              <div>{{ $row->user_name ?? '—' }}</div>
              @if(!empty($row->user_email))<div class="subtxt">{{ $row->user_email }}</div>@endif
            </td>
            <td>
              <div class="mono">{{ $action }}</div>
              @if($showMethod)<div class="subtxt">{{ $method }}</div>@endif
            </td>
            <td>
              <div>{{ $row->target_type ?? '—' }}</div>
              @if(!empty($row->target_key))<div class="subtxt break-all">{{ $row->target_key }}</div>@endif
            </td>
            <td>
              @if($row->status==='ok')
                <span class="badge" style="border:1px solid #14532d;background:rgba(16,185,129,.15);color:#bbf7d0">OK</span>
              @else
                <span class="badge" style="border:1px solid #7f1d1d;background:rgba(239,68,68,.15);color:#fecaca">ERROR</span>
              @endif
            </td>
            <td>{{ $row->ip }}</td>
            <td>
              <div>{{ $route !== '' ? $route : '—' }}</div>
              @if($showPath)<div class="subtxt break-all">{{ $path }}</div>@endif
            </td>
            <td>
              @if(!empty($row->message))<div class="subtxt" style="color:#fca5a5">{{ $row->message }}</div>@endif
              @if(!empty($row->data))
                <details class="text-xs mt-1">
                  <summary class="cursor-pointer" style="color:#7ab0ff">payload</summary>
                  <pre class="whitespace-pre-wrap" style="color:#e6edf7">{{ json_encode($row->data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
              @endif
            </td>
          </tr>
        @empty
          <tr><td class="px-4 py-6 text-center" style="color:var(--muted)" colspan="8">Belum ada log</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $logs->links() }}
  </div>
</div>
@endsection
