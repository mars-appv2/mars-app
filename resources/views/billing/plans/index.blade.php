@extends('layouts.app')
@section('content')
<div class="container mx-auto px-3">
  <div class="m-card p-5 mt-6">
    <div class="text-lg font-semibold text-slate-200 mb-3">Paket / Plan</div>

    <form method="POST" action="{{ route('billing.plans.store') }}" class="grid md:grid-cols-5 gap-3 items-end mb-4">
      @csrf
      <div><label class="m-lab">Nama</label><input name="name" class="m-inp" required></div>
      <div><label class="m-lab">Rate</label><input name="rate" class="m-inp" placeholder="10M/2M"></div>
      <div><label class="m-lab">PPP Profile</label><input name="ppp_profile" class="m-inp" placeholder="default"></div>
      <div><label class="m-lab">Group RADIUS</label><input name="groupname" class="m-inp" placeholder="Paket-10M"></div>
      <div><label class="m-lab">Harga / bln</label><input type="number" name="price_month" class="m-inp" required></div>
      <div class="md:col-span-5"><button class="m-btn m-btnp">Simpan</button></div>
    </form>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="text-slate-300" style="background:#0c101c">
          <th class="px-3 py-2 text-left">Nama</th>
          <th class="px-3 py-2 text-left">Rate</th>
          <th class="px-3 py-2 text-left">PPP Profile</th>
          <th class="px-3 py-2 text-left">Group</th>
          <th class="px-3 py-2 text-left">Harga</th>
          <th class="px-3 py-2 text-left">Aksi</th>
        </tr></thead>
        <tbody>
          @foreach($plans as $p)
          <tr class="border-t border-slate-800">
            <td class="px-3 py-2 text-slate-200">{{ $p->name }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $p->rate ?: '—' }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $p->ppp_profile ?: '—' }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $p->groupname ?: '—' }}</td>
            <td class="px-3 py-2 text-slate-300">Rp {{ number_format($p->price_month,0,',','.') }}</td>
            <td class="px-3 py-2">
              <form method="POST" action="{{ route('billing.plans.delete',$p) }}" onsubmit="return confirm('Hapus plan?')">
                @csrf @method('DELETE')
                <button class="m-btn">Hapus</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
