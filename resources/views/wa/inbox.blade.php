@extends('layouts.app')
@section('title','WhatsApp Inbox')

@section('content')
<div class="m-card p-5 space-y-3">
  <div class="flex items-center justify-between">
    <div class="text-lg font-semibold text-slate-200">Inbox</div>
    <a href="{{ route('wa.index') }}" class="m-btn">Kembali ke Gateway</a>
  </div>
  <form method="GET" class="flex gap-2">
    <input class="m-inp" name="q" value="{{ $q }}" placeholder="cari nomor / teks">
    <button class="m-btn m-btnp">Cari</button>
  </form>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-[var(--muted)]">
          <th class="py-2 pr-3">Waktu</th>
          <th class="py-2 pr-3">Dari</th>
          <th class="py-2 pr-3">Teks</th>
          <th class="py-2">Tipe</th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        <tr class="border-t border-[var(--line)]">
          <td class="py-2 pr-3">{{ \Carbon\Carbon::createFromTimestamp($r->ts ?: time())->format('Y-m-d H:i:s') }}</td>
          <td class="py-2 pr-3">{{ $r->from }}</td>
          <td class="py-2 pr-3">{{ $r->text }}</td>
          <td class="py-2">{{ $r->type }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="py-3 text-[var(--muted)]">Belum ada pesan.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
