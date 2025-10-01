@extends('layouts.app')
@section('title','Records — ' . ucfirst($group) . ' / ' . $key)

@section('content')
<div class="card p-4">
  <div class="label mb-3">Rekaman: {{ ucfirst($group) }} / {{ $key }}</div>
  @if(empty($rows) || count($rows)==0)
    <div class="text-slate-400">Belum ada rekaman.</div>
  @else
    <div class="grid md:grid-cols-4 gap-3">
      @foreach($rows as $r)
        <div class="rounded border border-[var(--line)] p-2">
          <div class="text-xs text-[var(--muted)] mb-1">{{ $r->period }} — {{ $r->created_at }}</div>
          <img src="{{ asset('storage/'. $r->png_path) }}" class="rounded" alt="png">
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
