@extends('staff.layouts.app')
@section('title','Users — Staff')

@section('content')
<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px">
    <form method="get" class="grid" style="display:grid;grid-template-columns:1fr auto;gap:8px;max-width:420px">
      <input class="input" name="q" value="{{ $q }}" placeholder="Cari nama/email/username/phone">
      <button class="btn" type="submit">Cari</button>
    </form>
    <a class="btn" href="{{ route('staff.users.create') }}">Tambah User</a>
  </div>

  @if(session('ok'))<div class="badge ok" style="display:inline-block;margin-bottom:8px">{{ session('ok') }}</div>@endif
  @if(session('err'))<div class="badge bad" style="display:inline-block;margin-bottom:8px">{{ session('err') }}</div>@endif

  <table class="table">
    <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>WA/HP</th><th>Aktif</th></tr></thead>
    <tbody>
      @foreach($users as $u)
      <tr>
        <td>{{ $u->id }}</td>
        <td>{{ $u->name }}</td>
        <td>{{ $u->email }}</td>
        <td>{{ $u->wa_number ?? $u->phone ?? '-' }}</td>
        <td>
          @if(\Illuminate\Support\Facades\Schema::hasColumn('users','is_active'))
            {!! $u->is_active ? '<span class="badge ok">Aktif</span>' : '<span class="badge bad">Nonaktif</span>' !!}
          @else
            <span class="badge">-</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div style="margin-top:10px">{{ $users->appends(['q'=>$q])->links() }}</div>
</div>
@endsection
