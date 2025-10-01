@extends('layouts.app')

@section('title','Settings — Telegram')

@section('content')
<div class="m-card p-4">
  <h3 class="mb-4">⚙️ Settings — Telegram Bot</h3>

  @if(session('success')) <div class="mb-3 p-2 rounded bg-green-900/40 text-green-300">{{ session('success') }}</div> @endif
  @if(session('err')) <div class="mb-3 p-2 rounded bg-red-900/40 text-red-300">{{ session('err') }}</div> @endif

  <div class="grid lg:grid-cols-2 gap-4">

    {{-- Form buat / edit bot --}}
    <div>
      <form method="POST" action="{{ route('settings.telegram.save') }}">
        @csrf
        <input type="hidden" name="id" id="bot_id" value="">
        <div class="m-inp mb-2">
          <label>Nama Bot</label>
          <input type="text" name="name" id="name" class="m-inp" required>
        </div>
        <div class="m-inp mb-2">
          <label>Username (contoh: @MarsRadiusBot)</label>
          <input type="text" name="username" id="username" class="m-inp">
        </div>
        <div class="m-inp mb-2">
          <label>Token (dari BotFather)</label>
          <input type="text" name="token" id="token" class="m-inp" required>
        </div>
        <div class="mb-3">
          <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1" checked class="mr-2"> Aktifkan bot
          </label>
        </div>
        <div class="flex gap-2">
          <button class="m-btnp" type="submit">Simpan</button>
          <button type="button" onclick="clearForm()" class="m-btn m-btn-outline">Bersihkan</button>
        </div>
      </form>
    </div>

    {{-- Daftar bot + aksi --}}
    <div>
      <h4 class="text-sm mb-3">Daftar Bot</h4>
      @if($bots->isEmpty())
        <div class="text-sm text-slate-400">Belum ada bot.</div>
      @else
        <div class="space-y-2">
          @foreach($bots as $bot)
            <div class="m-card p-3 flex items-center justify-between">
              <div>
                <div class="font-semibold">{{ $bot->name }} <span class="text-xs text-slate-400">({{ $bot->username }})</span></div>
                <div class="text-xs text-slate-400">Subscribers: {{ $bot->subscribers()->count() }}</div>
              </div>
              <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('settings.telegram.setwebhook', $bot->id) }}">
                  @csrf
                  <button class="m-btn m-btn-outline" type="submit">Set Webhook</button>
                </form>

                <button class="m-btn m-btn-outline" onclick="editBot({{ json_encode($bot) }})">Edit</button>

                <form method="POST" action="{{ route('settings.telegram.destroy', $bot->id) }}" onsubmit="return confirm('Hapus bot?')" >
                  @csrf
                  @method('DELETE')
                  <button class="m-btn text-red-300">Hapus</button>
                </form>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</div>

<script>
function editBot(bot) {
  document.getElementById('bot_id').value = bot.id || '';
  document.getElementById('name').value = bot.name || '';
  document.getElementById('username').value = bot.username || '';
  document.getElementById('token').value = bot.token || '';
}

function clearForm() {
  document.getElementById('bot_id').value = '';
  document.getElementById('name').value = '';
  document.getElementById('username').value = '';
  document.getElementById('token').value = '';
}
</script>
@endsection
