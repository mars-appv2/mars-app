@extends('layouts.app')
@section('title','Traffic — Records')

@section('content')
<div class="card p-4">
  <div class="label mb-3">Rekaman PNG</div>
  <div class="grid md:grid-cols-4 gap-3">
    @foreach($groups as $g)
      <a class="btn-primary text-center py-2 rounded" href="{{ route('traffic.records.group', $g) }}">
        {{ ucfirst($g) }} ({{ $counts[$g] ?? 0 }})
      </a>
    @endforeach
  </div>
</div>
@endsection
