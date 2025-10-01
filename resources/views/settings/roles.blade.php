@extends('layouts.app')
@section('title','Role User')
@section('content')
@if(session('ok'))<div class="mb-3 text-green-300">{{ session('ok') }}</div>@endif
<form method="POST" class="card p-4">@csrf
  <table class="w-full text-sm">
    <thead><tr class="text-left text-[var(--muted)]"><th class="p-2">User</th><th>Roles</th></tr></thead>
    <tbody>
    @foreach($users as $u)
      <tr class="border-t border-[var(--line)]">
        <td class="p-2">{{ $u->name }} <span class="opacity-70 text-xs">{{ $u->email }}</span></td>
        <td class="p-2 space-x-3">
          @foreach($roles as $r)
            <label class="inline-flex items-center gap-1">
              <input type="checkbox" name="roles[{{ $u->id }}][{{ $r }}]" {{ $u->hasRole($r)?'checked':'' }}>
              <span>{{ $r }}</span>
            </label>
          @endforeach
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
  <div class="mt-3"><button class="btn-primary px-4 py-2 rounded-lg">Simpan</button></div>
</form>
@endsection
