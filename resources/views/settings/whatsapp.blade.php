@extends('layouts.app')
@section('title','Setting WhatsApp')
@section('content')
@if(session('ok'))<div class="mb-3 text-green-300">{{ session('ok') }}</div>@endif
<form method="POST" class="card p-4 space-y-3">@csrf
  <div><label>API URL</label><input name="api_url" class="field w-full" value="{{ old('api_url',$url) }}"></div>
  <div><label>Token</label><input name="token" class="field w-full" value="{{ old('token',$token) }}"></div>
  <div><label>Default To</label><input name="default_to" class="field w-full" value="{{ old('default_to',$to) }}"></div>
  <button class="btn-primary px-4 py-2 rounded-lg">Save</button>
</form>
@endsection
