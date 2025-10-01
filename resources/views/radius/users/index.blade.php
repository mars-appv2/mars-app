@extends('layouts.app')
@section('content')
<div class="container mx-auto px-3">
  <div class="m-card p-5 mt-6">
    <div class="flex items-center justify-between mb-3">
      <div class="text-lg font-semibold text-slate-200">RADIUS Users</div>
      <form method="GET" class="flex gap-2">
        <input name="q" value="{{ $q }}" class="m-inp" placeholder="Cari username…">
        <button class="m-btn">Cari</button>
      </form>
    </div>

    <form method="POST" action="{{ route('radius.users.store') }}" class="grid md:grid-cols-5 gap-3 items-end mb-4">
      @csrf
      <div><label class="m-lab">Username</label><input name="username" class="m-inp" required></div>
      <div><label class="m-lab">Password</label><input name="password" class="m-inp" required></div>
      <div>
        <label class="m-lab">Paket</label>
        <select name="plan_id" class="m-inp">
          <option value="">— tanpa paket —</option>
          @foreach($plans as $p)
            <option value="{{ $p->id }}">{{ $p->name }} (Rp {{ number_format($p->price_month,0,',','.') }})</option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-2"><button class="m-btn m-btnp">Tambah</button></div>
    </form>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="text-slate-300" style="background:#0c101c">
          <th class="px-3 py-2 text-left">Username</th>
          <th class="px-3 py-2 text-left">Plan</th>
          <th class="px-3 py-2 text-left">Ubah Password</th>
        </tr></thead>
        <tbody>
          @foreach($users as $u)
          <tr class="border-t border-slate-800">
            <td class="px-3 py-2 text-slate-200">{{ $u->username }}</td>
            <td class="px-3 py-2 text-slate-300">
              {{ optional($subs[$u->username] ?? null)->plan->name ?? '—' }}
            </td>
            <td class="px-3 py-2">
              <form method="POST" action="{{ route('radius.users.pw') }}" class="flex gap-2">
                @csrf
                <input type="hidden" name="username" value="{{ $u->username }}">
                <input name="password" class="m-inp" placeholder="password baru" required>
                <button class="m-btn">Update</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $users->withQueryString()->links() }}</div>
  </div>
</div>
@endsection
