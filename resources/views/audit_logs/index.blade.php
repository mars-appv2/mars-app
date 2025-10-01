@extends('layouts.app')

@section('content')
<div id="audit-logs" class="container py-4">

  {{-- FINGERPRINT kecil supaya kelihatan view ini sudah dipakai --}}
  <div class="mb-2" style="opacity:.4;font-size:.8rem">view: audit_logs/index.blade.php • v3</div>

  {{-- FILTER CARD --}}
  <div class="al-card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="al-title m-0">Audit Log</div>
      <a href="{{ route('audit.logs.export', request()->all()) }}" class="al-link">Export CSV</a>
    </div>

    <form method="get" class="row g-2">
      <div class="col-12 col-md-4 col-lg-3">
        <input type="text" name="search" class="form-control al-input"
               placeholder="Cari nama/email/route/target" value="{{ request('search') }}">
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <select name="status" class="form-select al-input">
          <option value="">— status —</option>
          @foreach (['success','error','warning'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-2 col-lg-2">
        <button class="al-btn w-100" type="submit">FILTER</button>
      </div>
    </form>
  </div>

  {{-- TABLE CARD --}}
  <div class="al-table-wrap table-responsive">
    <table class="table table-hover al-table align-middle mb-0">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>User</th>
          <th>Action</th>
          <th>Target</th>
          <th>Status</th>
          <th>IP</th>
          <th>Route</th>
          <th>Info</th>
        </tr>
      </thead>
      <tbody>
      @forelse ($logs as $log)
        @php
          $badge = match($log->status) {
            'success' => 'al-badge ok',
            'warning' => 'al-badge warn',
            default   => 'al-badge err'
          };
        @endphp
        <tr>
          <td>
            {{ $log->created_at->format('Y-m-d') }}
            <span class="al-sub">{{ $log->created_at->format('H:i:s') }}</span>
          </td>
          <td>
            {{ $log->user_name ?? 'Guest' }}
            @if($log->user_email)<span class="al-sub">{{ $log->user_email }}</span>@endif
          </td>
          <td class="al-mono">{{ strtoupper($log->action) }}</td>
          <td>{{ $log->target }}</td>
          <td><span class="{{ $badge }}">{{ strtoupper($log->status) }}</span></td>
          <td>{{ $log->ip }}</td>
          <td>{{ $log->route }}</td>
          <td><a class="al-link" href="{{ route('audit.logs') }}?id={{ $log->id }}">payload</a></td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center py-4 al-sub">Belum ada data.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $logs->links() }}
  </div>
</div>
@endsection

@push('styles')
<style>
  /* ===== Scope ===== */
  #audit-logs { --bg:#0e1426; --panel:#111a2d; --panel2:#0f172a; --stroke:rgba(122,151,255,.18);
                --fg:#eaf0ff; --fg2:#c6d0f5; --muted:#98a4c8;
                --ok:#19c37d; --warn:#f6c453; --err:#ff6b6b; --prim1:#2f66ff; --prim2:#214ccc; }

  #audit-logs { color:var(--fg); }
  #audit-logs .al-title { font-size:1.05rem; font-weight:600; color:var(--fg2); }

  /* Card ala “Tambah MikroTik” */
  #audit-logs .al-card{
    background:var(--panel);
    border-radius:16px;
    border:1px solid var(--stroke);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.02), 0 12px 32px rgba(0,0,0,.35), 0 2px 0 rgba(122,151,255,.06);
  }

  /* Input & Select */
  #audit-logs .al-input,
  #audit-logs .form-control,
  #audit-logs .form-select{
    background:var(--panel2) !important;
    color:var(--fg) !important;
    border:1px solid rgba(122,151,255,.28) !important;
    border-radius:12px !important;
    height:42px; padding:10px 12px !important;
    outline:none !important; box-shadow: inset 0 0 0 .5px rgba(255,255,255,.04) !important;
  }
  #audit-logs .al-input::placeholder { color:var(--muted) !important; }
  #audit-logs .al-input:focus, 
  #audit-logs .form-control:focus, 
  #audit-logs .form-select:focus{
    border-color:rgba(117,160,255,.75) !important;
    box-shadow:0 0 0 3px rgba(117,160,255,.18) !important;
  }

  /* Button glossy */
  #audit-logs .al-btn{
    height:42px; border-radius:12px; padding:0 16px;
    color:#fff; font-weight:700; letter-spacing:.6px;
    background:linear-gradient(180deg, var(--prim1), var(--
