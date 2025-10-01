<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('app.name'))</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui']}}}}</script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="/css/theme-neon.css">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="theme-dark">
<div class="min-h-screen flex">
  <aside class="w-72 sidebar relative z-10" x-data="{open:{network:true,billing:true,admin:true,settings:true,traffic:true}}">
    <div class="px-5 py-4 flex items-center gap-3 border-b border-[var(--line)]">
      <img src="/img/logo.png" class="w-8 h-8 rounded" alt="logo">
      <div class="font-bold text-lg">{{ config('app.name') }}</div>
    </div>
    <nav class="p-3 text-sm space-y-2">
      @can('view dashboard')
      <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}">Dashboard</a>
      @endcan

      @can('manage mikrotik')
      <div>
        <button class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]" @click="open.network=!open.network">
          <span class="text-xs tracking-wider">NETWORK</span><span x-text="open.network ? '▾':'▸'"></span>
        </button>
        <div class="space-y-1 px-1" x-show="open.network" x-collapse>
          <a href="{{ route('mikrotik.index') }}" class="nav-item {{ request()->routeIs('mikrotik.*') ? 'nav-active' : '' }}">Mikrotik</a>
        </div>
      </div>
      @endcan

      @can('view traffic')
      <div>
        <button class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]" @click="open.traffic=!open.traffic">
          <span class="text-xs tracking-wider">TRAFFIC</span><span x-text="open.traffic ? '▾':'▸'"></span>
        </button>
        <div class="space-y-1 px-1" x-show="open.traffic" x-collapse>
          <a href="{{ route('traffic.targets') }}" class="nav-item {{ request()->routeIs('traffic.*') ? 'nav-active' : '' }}">Targets</a>
        </div>
      </div>
      @endcan

      @can('manage billing')
      <div>
        <button class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]" @click="open.billing=!open.billing">
          <span class="text-xs tracking-wider">BILLING</span><span x-text="open.billing ? '▾':'▸'"></span>
        </button>
        <div class="space-y-1 px-1" x-show="open.billing" x-collapse>
          <a href="{{ route('billing.index') }}" class="nav-item {{ request()->routeIs('billing.index') ? 'nav-active' : '' }}">Invoices</a>
          <a href="{{ route('billing.cashflow') }}" class="nav-item {{ request()->routeIs('billing.cashflow') ? 'nav-active' : '' }}">Cashflow</a>
        </div>
      </div>
      @endcan

      @can('manage radius')
      <a href="{{ route('radius.index') }}" class="nav-item {{ request()->routeIs('radius.index') ? 'nav-active' : '' }}">Radius Validation</a>
      @endcan

      @can('manage settings')
      <div>
        <button class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]" @click="open.settings=!open.settings">
          <span class="text-xs tracking-wider">PENGATURAN</span><span x-text="open.settings ? '▾':'▸'"></span>
        </button>
        <div class="space-y-1 px-1" x-show="open.settings" x-collapse>
          <a href="{{ route('settings.telegram') }}" class="nav-item {{ request()->routeIs('settings.telegram') ? 'nav-active' : '' }}">Telegram Bot</a>
          <a href="{{ route('settings.whatsapp') }}" class="nav-item {{ request()->routeIs('settings.whatsapp') ? 'nav-active' : '' }}">WhatsApp Gateway</a>
          <a href="{{ route('settings.payment') }}" class="nav-item {{ request()->routeIs('settings.payment') ? 'nav-active' : '' }}">Payment Gateway</a>
          <a href="{{ route('settings.roles') }}" class="nav-item {{ request()->routeIs('settings.roles') ? 'nav-active' : '' }}">Role User</a>
        </div>
      </div>
      @endcan

      <form method="POST" action="{{ route('logout') }}" class="px-2">@csrf
        <button class="w-full nav-item text-left text-red-300">Logout</button>
      </form>
    </nav>
  </aside>

  <main class="flex-1">
    <header class="topbar text-white px-6 py-4 shadow">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div class="font-semibold text-lg">@yield('title','Overview')</div>
        <div class="text-sm opacity-80">{{ auth()->user()->name ?? '' }}</div>
      </div>
    </header>
    <div class="max-w-7xl mx-auto px-6 py-6">
      @yield('content')
    </div>
  </main>
</div>
    <script src="{{ asset('js/mon-groups-v1.js') }}?v=1" defer></script>
</body>
</html>
