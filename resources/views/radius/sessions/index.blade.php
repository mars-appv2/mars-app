@extends('layouts.app')
@section('content')
<div class="container mx-auto px-3">
  <div class="m-card p-5 mt-6">
    <div class="flex items-center justify-between mb-3">
      <div class="text-lg font-semibold text-slate-200">Active Sessions</div>
      <form method="GET" class="flex gap-2">
        <input name="q" value="{{ $q }}" class="m-inp" placeholder="Cari username…">
        <button class="m-btn">Cari</button>
      </form>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="text-slate-300" style="background:#0c101c">
          <th class="px-3 py-2 text-left">Mulai</th>
          <th class="px-3 py-2 text-left">User</th>
          <th class="px-3 py-2 text-left">NAS</th>
          <th class="px-3 py-2 text-left">IP</th>
          <th class="px-3 py-2 text-left">Bytes</th>
        </tr></thead>
        <tbody>
          @foreach($sessions as $s)
          <tr class="border-t border-slate-800">
            <td class="px-3 py-2 text-slate-300">{{ $s->acctstarttime }}</td>
            <td class="px-3 py-2 text-slate-200">{{ $s->username }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $s->nasipaddress }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $s->framedipaddress }}</td>
            <td class="px-3 py-2 text-slate-300">
              {{ number_format($s->acctinputoctets + $s->acctoutputoctets) }} B
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $sessions->withQueryString()->links() }}</div>
  </div>
</div>
@endsection
