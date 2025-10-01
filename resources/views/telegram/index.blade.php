@extends('layouts.app')

@section('title', 'Telegram Bots')

@section('content')
<div class="m-card p-4">
  <h3 class="mb-4">📲 Telegram Bots & Broadcast</h3>

  @if(session('success'))
    <div class="mb-3 p-2 rounded bg-green-900/40 text-green-300">{{ session('success') }}</div>
  @endif
  @if(session('err'))
    <div class="mb-3 p-2 rounded bg-red-900/40 text-red-300">{{ session('err') }}</div>
  @endif
  @if(session('warning'))
    <div class="mb-3 p-2 rounded bg-yellow-900/40 text-yellow-200">{{ session('warning') }}</div>
  @endif

  <div class="grid lg:grid-cols-3 gap-4">
    {{-- Left: Broadcast Form --}}
    <div class="col-span-2">
      <form method="POST" action="{{ route('telegram.broadcast') }}">
        @csrf
        <div class="m-inp mb-3">
          <label>Pilih Bot</label>
          <select name="bot_id" class="m-inp" required>
            <option value="">-- Pilih Bot --</option>
            @foreach($bots as $bot)
              <option value="{{ $bot->id }}">{{ $bot->name }} ({{ $bot->username }})</option>
            @endforeach
          </select>
        </div>

        <div class="m-inp mb-3">
          <label>Pesan Broadcast</label>
          <textarea name="message" class="m-inp" rows="6" placeholder="Tulis pesan broadcast..."></textarea>
        </div>

        <div class="flex gap-2">
          <button class="m-btnp" type="submit">Kirim Broadcast</button>
          <a href="{{ route('settings.telegram') }}" class="m-btn m-btn-outline">Pengaturan Bot</a>
        </div>
      </form>
    </div>

    {{-- Right: Statistik singkat --}}
    <div>
      <div class="m-card p-3">
        <h4 class="text-sm mb-2">Ringkasan</h4>
        <div class="text-xs text-slate-400">
          <div>Jumlah Bot: <strong>{{ $bots->count() }}</strong></div>
          <div>Total Subscribers: <strong>{{ $bots->sum(fn($b)=> $b->subscribers()->count()) }}</strong></div>
        </div>
      </div>

      {{-- List bot singkat --}}
      <div class="mt-3 m-card p-3">
        <h4 class="text-sm mb-2">Daftar Bot</h4>
        @if($bots->isEmpty())
          <div class="text-sm text-slate-400">Belum ada bot — buka Settings → Telegram untuk membuat.</div>
        @else
          <ul class="space-y-2 text-sm">
            @foreach($bots as $bot)
              <li class="flex items-start justify-between">
                <div>
                  <div class="font-semibold">{{ $bot->name }} <span class="text-xs text-slate-400">({{ $bot->username }})</span></div>
                  <div class="text-xs text-slate-400">Subscribers: {{ $bot->subscribers()->count() }}</div>
                </div>
                <div class="text-right">
                  @if(optional($bot->settings)['webhook_set'] ?? false)
                    <div class="text-xs text-green-300">Webhook ✔</div>
                  @else
                    <div class="text-xs text-yellow-300">Webhook ❌</div>
                  @endif
                </div>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
