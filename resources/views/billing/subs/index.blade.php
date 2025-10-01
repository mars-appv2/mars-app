@extends('layouts.app')
@section('content')
<div class="container mx-auto px-3">
  <div class="m-card p-5 mt-6">
    <div class="text-lg font-semibold text-slate-200 mb-3">Subscriptions</div>

    <form method="POST" action="{{ route('billing.subs.store') }}" class="grid md:grid-cols-4 gap-3 items-end mb-4">
      @csrf
      <div><label class="m-lab">Username</label><input name="username" class="m-inp" required></div>
      <div>
        <label class="m-lab">Paket</label>
        <select name="plan_id" class="m-inp" required>
          @foreach($plans as $p)
            <option value="{{ $p->id }}">{{ $p->name }} (Rp {{ number_format($p->price_month,0,',','.') }})</option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-2"><button class="m-btn m-btnp">Simpan</button></div>
    </form>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="text-slate-300" style="background:#0c101c">
          <th class="px-3 py-2 text-left">Username</th>
          <th class="px-3 py-2 text-left">Plan</th>
          <th class="px-3 py-2 text-left">Status</th>
          <th class="px-3 py-2 text-left">Start</th>
          <th class="px-3 py-2 text-left">End</th>
        </tr></thead>
        <tbody>
          @foreach($subs as $s)
          <tr class="border-t border-slate-800">
            <td class="px-3 py-2 text-slate-200">{{ $s->username }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $s->plan->name }}</td>
            <td class="px-3 py-2">
              <span class="badge" style="border-color:#14532d;background:rgba(16,185,129,.15);color:#bbf7d0">{{ strtoupper($s->status) }}</span>
            </td>
            <td class="px-3 py-2 text-slate-300">{{ $s->started_at ?: '—' }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $s->ends_at ?: '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $subs->links() }}</div>
  </div>
</div>
@endsection
