@extends('layouts.app')
@section('title','Login')
@section('content')
<div class="min-h-[60vh] flex items-center justify-center relative">
  <div class="absolute inset-0 -z-10 animate-pulse" style="background: radial-gradient(600px 200px at 30% 30%, rgba(124,58,237,.15), transparent),
  radial-gradient(400px 160px at 70% 60%, rgba(34,211,238,.15), transparent)"></div>
  <form method="POST" action="{{ route('login') }}" class="card p-6 w-full max-w-sm space-y-3">@csrf
    <div class="flex items-center gap-3 mb-2">
      <img src="/img/logo.png" class="w-10 h-10" alt="logo">
      <div>
        <div class="text-xl font-bold">PT MARS DATA TELEKOMUNIKASI</div>
        <div class="text-xs opacity-70">Silakan masuk</div>
      </div>
    </div>
    <input type="email" name="email" class="field" placeholder="Email" required autofocus>
    <input type="password" name="password" class="field" placeholder="Password" required>
    <label class="inline-flex items-center gap-2">
      <input type="checkbox" name="remember"> <span class="text-sm">Ingat saya</span>
    </label>
    <button class="btn-primary px-4 py-2 rounded-lg w-full">Login</button>
    <div class="text-right text-sm"><a href="{{ route('password.request') }}">Lupa password?</a></div>
  </form>
</div>
@endsection
