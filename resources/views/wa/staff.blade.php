@extends('layouts.app')
@section('title','WA Staff')

@section('content')
@if(session('ok')) <div class="m-card p-3 mb-4 text-green-300">{{ session('ok') }}</div> @endif
@if(session('err'))<div class="m-card p-3 mb-4 text-red-300">{{ session('err') }}</div> @endif

<div class="m-card p-5">
  <div class="flex items-center justify-between">
    <div class="text-lg font-semibold text-slate-200">Daftar WA Staff</div>
    <form method="GET" class="flex gap-2">
      <input name="q" value="{{ $q }}" class="m-inp" placeholder="cari nama/nomor">
      <button class="m-btn m-btnp m-btn-icon">Cari</button>
    </form>
  </div>

  <form method="POST" class="grid md:grid-cols-5 gap-3 mt-4">
    @csrf
    <div><label class="m-lab">Nama</label><input name="name" class="m-inp" required></div>
    <div><label class="m-lab">Nomor (628xx)</label><input name="phone" class="m-inp" required></div>
    <div>
      <label class="m-lab">Role</label>
      <select name="role" class="m-inp">
        <option value="noc">NOC</option>
        <option value="teknisi">Teknisi</option>
        <option value="staff" selected>Staff</option>
      </select>
    </div>
    <div class="flex items-end">
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="active" value="1" checked class="m-chk"> Aktif
      </label>
    </div>
    <div class="flex items-end"><button class="m-btn m-btnp m-btn-icon">Simpan</button></div>
  </form>

  <div class="overflow-auto mt-4">
    <table class="w-full text-sm">
      <thead><tr class="text-[var(--muted)]"><th>Nama</th><th>Phone</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr class="border-t border-[var(--line)]">
          <td class="py-2 text-slate-200">{{ $r->name }}</td>
          <td class="py-2">{{ $r->phone }}</td>
          <td class="py-2">{{ strtoupper($r->role) }}</td>
          <td class="py-2">{{ $r->active ? 'AKTIF' : 'NON' }}</td>
          <td class="py-2">
            <form method="POST" action="{{ route('wa.staff.delete',$r->id) }}" onsubmit="return confirm('Hapus?')">
              @csrf @method('DELETE')
              <button class="m-btn m-btn-ghost m-btn-icon">Hapus</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="py-3 text-[var(--muted)]">Kosong</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
