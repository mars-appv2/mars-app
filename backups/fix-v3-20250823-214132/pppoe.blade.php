@extends('layouts.app')

@section('content')
@php
  // Aktifkan set username yang sedang active (dari $active jika ada)
  $activeSet = collect($active ?? [])->pluck('name')->flip();
@endphp

<div class="container-fluid px-3">
  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('mikrotik.index') }}" class="btn btn-outline-secondary btn-sm">Table List</a>
    <a href="{{ route('mikrotik.dashboard', $mikrotik) }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    <a href="{{ route('mikrotik.pppoe', $mikrotik) }}" class="btn btn-primary btn-sm">PPPoE</a>
    <a href="{{ route('mikrotik.ipstatic', $mikrotik) }}" class="btn btn-outline-secondary btn-sm">IP Static</a>
    <div class="ms-auto text-muted small">
      PPPoE — <strong>{{ $mikrotik->name }}</strong> ({{ $mikrotik->host }}:{{ $mikrotik->port }})
    </div>
  </div>

  <div class="row g-3">
    {{-- Tambah Client --}}
    <div class="col-lg-6">
      <div class="card" style="background:#0f1423;border:1px solid #1f2a45;">
        <div class="card-body">
          <h6 class="mb-3 text-light">Tambah Client</h6>
          <form id="ppp-add" onsubmit="return false;">
            @csrf
            <div class="mb-2">
              <label class="form-label text-muted">Username</label>
              <input type="text" class="form-control text-light" name="name" placeholder="mis: user01" required>
            </div>
            <div class="mb-2">
              <label class="form-label text-muted">Password</label>
              <input type="text" class="form-control text-light" name="password" placeholder="•••••" required>
            </div>
            <div class="mb-2">
              <label class="form-label text-muted">Profil</label>
              @php
                // Ambil profil dari RouterOS (fallback jika tidak tersedia)
                // Jika controller sudah menyiapkan $profiles, pakai itu.
                $profiles = $profiles ?? [];
                if (empty($profiles) && !empty($secrets)) {
                  $profiles = collect($secrets)->pluck('profile')->filter()->unique()->sort()->values()->all();
                }
                if (empty($profiles)) { $profiles = ['default']; }
              @endphp
              <select class="form-select text-light" name="profile">
                @foreach($profiles as $p)
                  <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="ppp-add-record" name="record">
              <label class="form-check-label text-muted" for="ppp-add-record">Rekam trafik client ini</label>
            </div>
            <button class="btn btn-primary">SIMPAN</button>
          </form>
        </div>
      </div>
    </div>

    {{-- Tambah Profil --}}
    <div class="col-lg-6">
      <div class="card" style="background:#0f1423;border:1px solid #1f2a45;">
        <div class="card-body">
          <h6 class="mb-3 text-light">Tambah Profil</h6>
          <form id="ppp-profile-add" onsubmit="return false;">
            @csrf
            <div class="mb-2">
              <label class="form-label text-muted">Nama Profil</label>
              <input type="text" class="form-control text-light" name="name" placeholder="mis: 10M" required>
            </div>
            <div class="mb-2">
              <label class="form-label text-muted">Rate-limit (up/down)</label>
              <input type="text" class="form-control text-light" name="rate" placeholder="10M/10M">
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">Parent Queue (opsional)</label>
              <input type="text" class="form-control text-light" name="parent" placeholder="mis: global">
            </div>
            <button class="btn btn-primary">SIMPAN</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Kelola Client --}}
  <div class="card mt-4" style="background:#0f1423;border:1px solid #1f2a45;">
    <div class="card-body">
      <div class="d-flex align-items-center mb-2">
        <h6 class="mb-0 text-light">Kelola Client</h6>
        <div class="ms-auto" style="max-width:340px;">
          <input id="ppp-search" type="text" class="form-control text-light" placeholder="Cari username / profil / status …">
        </div>
      </div>

      @php
        $total = is_countable($secrets ?? []) ? count($secrets) : 0;
        $aktif = is_countable($active ?? []) ? count($active) : 0;
        $non   = $total - $aktif;
      @endphp
      <div class="text-muted small mb-2">
        Total: <strong>{{ $total }}</strong> — Active: <span class="badge bg-success">{{ $aktif }}</span>
        — Inactive: <span class="badge bg-secondary">{{ $non }}</span>
      </div>

      <div class="table-responsive">
        <table id="ppp-table" class="table table-dark table-hover align-middle mb-0" style="--bs-table-bg:#0f1423;">
          <thead>
            <tr class="text-muted">
              <th>USERNAME</th>
              <th style="width:220px;">PROFIL</th>
              <th>SERVICE</th>
              <th>STATUS</th>
              <th>REKAM</th>
              <th class="text-end">AKSI</th>
            </tr>
          </thead>
          <tbody>
          @foreach(($secrets ?? []) as $u)
            @php
              $uname = is_array($u) ? ($u['name'] ?? '') : ($u->name ?? '');
              $prof  = is_array($u) ? ($u['profile'] ?? '') : ($u->profile ?? '');
              $svc   = is_array($u) ? ($u['service'] ?? 'pppoe') : ($u->service ?? 'pppoe');
              $isActive = $activeSet->has($uname);
              // cek rekam (optional; query ringan per baris)
              $isRecorded = \App\Models\MonitorTarget::where([
                'mikrotik_id'=>$mikrotik->id,
                'target_type'=>'pppoe',
                'target_key'=>$uname
              ])->where('enabled',true)->exists();
            @endphp
            <tr data-row="{{ $uname }}">
              <td class="fw-semibold">{{ $uname }}</td>
              <td>
                <div class="d-flex gap-2">
                  <select class="form-select form-select-sm text-light ppp-prof" data-user="{{ $uname }}" style="min-width:180px;">
                    @foreach($profiles as $p)
                      <option value="{{ $p }}" @selected($p==$prof)>{{ $p }}</option>
                    @endforeach
                  </select>
                  <button class="btn btn-outline-primary btn-sm ppp-prof-save" data-user="{{ $uname }}">Ubah</button>
                </div>
              </td>
              <td class="text-muted">{{ $svc }}</td>
              <td>
                @if($isActive)
                  <span class="badge bg-success">active</span>
                @else
                  <span class="badge bg-secondary">inactive</span>
                @endif
              </td>
              <td>
                <div class="form-check">
                  <input class="form-check-input ppp-record" type="checkbox" data-user="{{ $uname }}" @checked($isRecorded)>
                </div>
              </td>
              <td class="text-end">
                @if($isActive)
                  <button class="btn btn-outline-warning btn-sm ppp-disable" data-user="{{ $uname }}">Disable</button>
                @else
                  <button class="btn btn-outline-success btn-sm ppp-enable" data-user="{{ $uname }}">Enable</button>
                @endif
                <button class="btn btn-outline-danger btn-sm ms-2 ppp-del" data-user="{{ $uname }}">Delete</button>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- gaya kecil agar teks input/select tetap terlihat di tema gelap --}}
<style>
  .form-control.text-light, .form-select.text-light{
    background:#0e1321 !important; color:#e5ecff !important; border-color:#26314f !important;
  }
  ::placeholder { color:#9fb2d6 !important; opacity:.7; }
</style>

{{-- JS interaksi PPPoE --}}
<script src="{{ asset('js/pppoe-ui-v2.js') }}?v=1" defer></script>
@endsection
