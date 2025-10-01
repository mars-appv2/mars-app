@extends('layouts.app')
@section('title','Finance — Nomor Akun')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="flex items-center justify-between mb-4">
    <div class="text-lg text-slate-200 font-semibold">Nomor Akun (Chart of Accounts)</div>
  </div>

  @if(session('ok'))<div class="text-green-400 mb-3">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="text-red-400 mb-3">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('finance.accounts.store') }}" class="grid md:grid-cols-6 gap-3 mb-6">
    @csrf
    <input class="m-inp md:col-span-1" name="code" placeholder="Kode" required>
    <input class="m-inp md:col-span-2" name="name" placeholder="Nama Akun" required>
    <select class="m-inp md:col-span-1" name="type" required>
      <option value="">Tipe</option>
      <option value="1">Asset</option>
      <option value="2">Liability</option>
      <option value="3">Equity</option>
      <option value="4">Revenue</option>
      <option value="5">Expense</option>
    </select>
    <select class="m-inp md:col-span-1" name="parent_id">
      <option value="">Parent (opsional)</option>
      @foreach($parents as $p)
        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
      @endforeach
    </select>
    <div class="flex items-center gap-4 md:col-span-1">
      <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_cash" value="1"> <span>Kas/Bank</span></label>
      <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> <span>Aktif</span></label>
    </div>
    <div class="md:col-span-6">
      <button class="m-btn">Tambah Akun</button>
    </div>
  </form>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-300">
        <tr>
          <th class="text-left p-2">Kode</th>
          <th class="text-left p-2">Nama</th>
          <th class="text-left p-2">Tipe</th>
          <th class="text-left p-2">Kas</th>
          <th class="text-left p-2">Aktif</th>
          <th class="text-left p-2">Parent</th>
          <th class="p-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($accounts as $a)
          <tr class="border-t border-slate-700">
            <td class="p-2">{{ $a->code }}</td>
            <td class="p-2">{{ $a->name }}</td>
            <td class="p-2">{{ $a->typeLabel() }}</td>
            <td class="p-2">{{ $a->is_cash ? 'Ya' : '-' }}</td>
            <td class="p-2">{{ $a->is_active ? 'Ya' : '-' }}</td>
            <td class="p-2">{{ optional($a->parent)->code }}</td>
            <td class="p-2">
              <details>
                <summary class="cursor-pointer text-blue-300">Edit</summary>
                <form method="POST" action="{{ route('finance.accounts.update',$a) }}" class="grid md:grid-cols-6 gap-2 mt-2">
                  @csrf
                  <input class="m-inp" name="code" value="{{ $a->code }}">
                  <input class="m-inp md:col-span-2" name="name" value="{{ $a->name }}">
                  <select class="m-inp" name="type">
                    @for($i=1;$i<=5;$i++)
                      <option value="{{ $i }}" @if($a->type==$i) selected @endif>{{ ['','Asset','Liability','Equity','Revenue','Expense'][$i] }}</option>
                    @endfor
                  </select>
                  <select class="m-inp" name="parent_id">
                    <option value="">(none)</option>
                    @foreach($parents as $p)
                      <option value="{{ $p->id }}" @if($a->parent_id==$p->id) selected @endif>{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                  </select>
                  <div class="flex gap-4 items-center">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_cash" value="1" @if($a->is_cash) checked @endif> <span>Kas</span></label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @if($a->is_active) checked @endif> <span>Aktif</span></label>
                  </div>
                  <div class="md:col-span-6 flex gap-2">
                    <button class="m-btn">Simpan</button>
                  </div>
                </form>
                <form method="POST" action="{{ route('finance.accounts.delete',$a) }}" onsubmit="return confirm('Hapus akun {{ $a->code }}?')" class="mt-2">
                  @csrf @method('DELETE')
                  <button class="m-btnp">Hapus</button>
                </form>
              </details>
            </td>
          </tr>
        @empty
          <tr><td class="p-3" colspan="7">Belum ada akun.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
