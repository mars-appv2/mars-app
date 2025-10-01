<div class="m-card p-4 mb-4">
  <div class="text-slate-200 font-semibold mb-3">Hapus Massal User</div>
  <form method="POST" action="{{ route('radius.users.bulkDelete') }}"
        class="grid lg:grid-cols-12 gap-3 items-end"
        onsubmit="return confirm('YAKIN hapus semua user sesuai filter? Tindakan ini tidak bisa dibatalkan.');">
    @csrf
    <input type="hidden" name="confirm" value="DELETE">
    <div class="lg:col-span-4">
      <label class="m-lab">Perangkat (wajib)</label>
      <select name="mikrotik_id" class="m-inp" required>
        <option value="">— pilih perangkat —</option>
        @foreach($devices as $d)
          <option value="{{ $d->id }}">{{ $d->name }} — {{ $d->host }}</option>
        @endforeach
      </select>
    </div>
    <div class="lg:col-span-4">
      <label class="m-lab">Plan/PPP Profile (opsional)</label>
      <select name="plan" class="m-inp">
        <option value="">— semua plan —</option>
        @foreach($plans as $pl)
          <option value="{{ $pl }}">{{ $pl }}</option>
        @endforeach
      </select>
    </div>
    <div class="lg:col-span-3">
      <label class="m-lab">Juga hapus subscriptions</label>
      <select name="with_subs" class="m-inp">
        <option value="0">Tidak</option>
        <option value="1">Ya, hapus</option>
      </select>
    </div>
    <div class="lg:col-span-1">
      <label class="m-lab opacity-0">.</label>
      <button class="m-btn bg-red-600/80 hover:bg-red-600 w-full">Hapus</button>
    </div>
  </form>
</div>
