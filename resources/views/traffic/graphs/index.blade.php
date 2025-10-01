@extends('layouts.app')
@section('title','Traffic — Graph Sets')
@section('content')
<div class="card p-4">
  <div class="label mb-2">Traffic Graphs</div>
  <div class="grid md:grid-cols-3 gap-3">
    <a class="m-btn w-full text-center" href="{{ route('traffic.graphs.interfaces') }}">Interface Graphs</a>
    <a class="m-btn w-full text-center" href="{{ route('traffic.graphs.pppoe') }}">PPPoE IP Public</a>
    <a class="m-btn w-full text-center" href="{{ route('traffic.graphs.content') }}">Content Apps</a>
  </div>
</div>
@endsection
