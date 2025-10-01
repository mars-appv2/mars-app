<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('app.name'))</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] } } }
    }
  </script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <link rel="stylesheet" href="/css/theme-neon.css">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <style>
    /* tambahan kecil agar mobile nyaman tanpa ganggu tema */
    .m-btn-sm { padding: .375rem .6rem; font-size: .8125rem; border-radius: .5rem; }
    .topbar { position: sticky; top: 0; z-index: 40; background: rgba(15, 15, 26, .85); backdrop-filter: blur(6px); }
    @media (max-width: 1024px){
      .sidebar { background: var(--panel, #0f0f1a); }
    }
  </style>
  @stack('styles')
</head>
<body class="theme-dark">
<div
  x-data="{ openSidebar:false, open:{ network:true, traffic:true, settings:true, roleuser:true } }"
  class="min-h-screen lg:flex">

  {{-- ===== Backdrop mobile (ketuk untuk menutup sidebar) ===== --}}
  <div
    class="fixed inset-0 bg-black/40 z-20 lg:hidden"
    x-show="openSidebar"
    x-transition.opacity
    @click="openSidebar=false">
  </div>

  {{-- ========== SIDEBAR (off-canvas di mobile) ========== --}}
  <aside
    class="sidebar w-72 z-30 fixed inset-y-0 left-0 transform transition-transform duration-200 -translate-x-full
           lg:translate-x-0 lg:relative lg:z-10 border-r border-[var(--line)]"
    :class="openSidebar ? 'translate-x-0' : '-translate-x-full'">
    <div class="px-5 py-4 flex items-center gap-3 border-b border-[var(--line)]">
      <img src="/img/logo.png" class="w-8 h-8 rounded" alt="logo">
      <div class="font-bold text-lg truncate">{{ config('app.name') }}</div>
      {{-- tombol close (hanya mobile) --}}
      <button class="ml-auto lg:hidden text-[var(--muted)] hover:text-white" @click="openSidebar=false" title="Tutup">
        ✕
      </button>
    </div>

    <nav class="p-3 text-sm space-y-2">

      {{-- DASHBOARD --}}
      @can('view dashboard')
        <a href="{{ route('dashboard') }}"
           class="nav-item {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}">
          Dashboard
        </a>
      @endcan

      {{-- ========== NETWORK ========== --}}
      @can('manage mikrotik')
        <div>
          <button type="button"
                  class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]"
                  @click="open.network = !open.network">
            <span class="text-xs tracking-wider">NETWORK</span>
            <span x-text="open.network ? '▾' : '▸'"></span>
          </button>
          <div class="space-y-1 px-1" x-show="open.network" x-collapse>
            <a href="{{ route('mikrotik.index') }}"
               class="nav-item {{ request()->routeIs('mikrotik.*') ? 'nav-active' : '' }}">
              Mikrotik
            </a>
            {{-- Backups (jika sudah kamu aktifkan rute-nya) --}}
            @if(Route::has('backups.index'))
              <a href="{{ route('backups.index') }}"
                 class="nav-item {{ request()->routeIs('backups.index') || request()->routeIs('mikrotik.backups*') ? 'nav-active' : '' }}">
                Backups
              </a>
            @endif
          </div>
        </div>
      @endcan

      {{-- ========== TRAFFIC ========== --}}
      @can('view traffic')
  	<div>
    	  <button type="button"
            	  class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]"
            	  @click="open.traffic = !open.traffic">
      	    <span class="text-xs tracking-wider">NMS</span>
      	    <span x-text="open.traffic ? '▾' : '▸'"></span>
    	  </button>

    	  <div class="space-y-1 px-1" x-show="open.traffic" x-collapse>
      	    <a href="{{ route('traffic.graphs.interfaces') }}"
               class="nav-item {{ request()->routeIs('traffic.graphs.interfaces') ? 'nav-active' : '' }}">
              Interfaces
      	    </a>
      	    <a href="{{ route('traffic.graphs.pppoe') }}"
               class="nav-item {{ request()->routeIs('traffic.graphs.pppoe') ? 'nav-active' : '' }}">
              PPPoE
      	    </a>
      	    <a href="{{ route('traffic.graphs.ip') }}"
               class="nav-item {{ request()->routeIs('traffic.graphs.ip') ? 'nav-active' : '' }}">
              IP Public
      	    </a>
      	    <a href="{{ route('traffic.graphs.content') }}"
               class="nav-item {{ request()->routeIs('traffic.graphs.content') ? 'nav-active' : '' }}">
              Content Apps
      	    </a>
    	  </div>
  	</div>
      @endcan

      {{-- ========== RADIUS (NON-COLLAPSIBLE) ========== --}}
      @can('manage radius')
        <div class="px-3 py-2 text-[var(--muted)] text-xs tracking-wider">RADIUS</div>
        <div class="space-y-1 px-1">
          <a href="{{ route('radius.users') }}"
             class="nav-item {{ request()->routeIs('radius.users') ? 'nav-active' : '' }}">
            Users
          </a>
          <a href="{{ route('radius.sessions') }}"
             class="nav-item {{ request()->routeIs('radius.sessions') ? 'nav-active' : '' }}">
            Sessions
          </a>
        </div>
      @endcan

      {{-- COMMUNICATION: Telegram (hanya untuk role/permission yang sesuai) --}}
      @can('manage telegram')
        @if(Route::has('telegram.index'))
          <div class="px-3 py-2 text-[var(--muted)] text-xs tracking-wider">COMMUNICATION</div>
          <div class="space-y-1 px-1">
            <a href="{{ route('telegram.index') }}"
               class="nav-item {{ request()->routeIs('telegram.*') ? 'nav-active' : '' }}">
              Telegram
            </a>
          </div>
        @endif
      @endcan

      {{-- ========== BILLING (NON-COLLAPSIBLE) ========== --}}
      @can('manage billing')
  	<div class="px-3 py-2 text-[var(--muted)] text-xs tracking-wider">BILLING</div>
  	<div class="space-y-1 px-1">
    	  <a href="{{ route('billing.plans') }}"
       	    class="nav-item {{ request()->routeIs('billing.plans') ? 'nav-active' : '' }}">
      	   Paket
    	  </a>
    	  <a href="{{ route('billing.subs') }}"
       	    class="nav-item {{ request()->routeIs('billing.subs') ? 'nav-active' : '' }}">
      	   Subscriptions
    	  </a>
    	  <a href="{{ route('billing.invoices') }}"
       	    class="nav-item {{ request()->routeIs('billing.invoices') ? 'nav-active' : '' }}">
      	   Invoices
    	  </a>
    	  <a href="{{ route('billing.payments') }}"
       	    class="nav-item {{ request()->routeIs('billing.payments') ? 'nav-active' : '' }}">
      	   Pembayaran
    	  </a>
  	</div>
      @endcan

      @can('manage finance')
        <div class="mt-2">
	  <div class="text-xs uppercase tracking-wider text-slate-400 mb-1">Finance</div>
	  <div class="space-y-1">
	    <a href="{{ route('finance.kas') }}" 
	      class="nav-item {{ request()->routeIs('finance.kas') ? 'nav-active' : '' }}">
	     Kas Keluar/Masuk
	    </a>
	    <a href="{{ route('finance.cash') }}" 
	      class="nav-item {{ request()->routeIs('finance.cash') ? 'nav-active' : '' }}">
	     Lajur Kas
	    </a>
	    <a href="{{ route('finance.ledger') }}" 
	      class="nav-item {{ request()->routeIs('finance.ledger') ? 'nav-active' : '' }}">
	     Buku Besar
	    </a>
	    <a href="{{ route('finance.trial') }}" 
	      class="nav-item {{ request()->routeIs('finance.trial') ? 'nav-active' : '' }}">
	     Neraca Percobaan
	    </a>
	    <a href="{{ route('finance.balance') }}" 
	      class="nav-item {{ request()->routeIs('finance.balance') ? 'nav-active' : '' }}">
	     Neraca
	    </a>
	    <a href="{{ route('finance.accounts') }}" 
	      class="nav-item {{ request()->routeIs('finance.accounts') ? 'nav-active' : '' }}">
	     Nomor Akun
	    </a>
	    <a href="{{ route('finance.jurnal') }}" 
	      class="nav-item {{ request()->routeIs('finance.jurnal') ? 'nav-active' : '' }}">
	     Jurnal Umum
	    </a>
	  </div>
        </div>
      @endcan

      {{-- ========== PENGATURAN ========== --}}
      @can('manage settings')
        <div>
          <button type="button"
                  class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]"
                  @click="open.settings = !open.settings">
            <span class="text-xs tracking-wider">PENGATURAN</span>
            <span x-text="open.settings ? '▾' : '▸'"></span>
          </button>
          <div class="space-y-1 px-1" x-show="open.settings" x-collapse>
            @if(Route::has('settings.telegram'))
	      <a href="{{ route('settings.telegram') }}"
                 class="nav-item {{ request()->routeIs('settings.telegram') ? 'nav-active' : '' }}">
                Telegram Bot
              </a>
            @endif
            @if(Route::has('settings.whatsapp'))
              <a href="{{ route('wa.index') }}" class="side-link {{ request()->routeIs('wa.*')?'active':'' }}">
    		{{-- ikon chat kecil --}}
    		<svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
      		  <path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
    		</svg>
    	        WhatsApp Gateway
  	      </a>
	      <a href="{{ route('tickets.index', [], false) }}" class="m-navlink {{ request()->is('tickets') ? 'active' : '' }}">
    		<span class="m-navicon">
      		  <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 7h18v6h-3a3 3 0 0 0-6 0H9a3 3 0 0 0-6 0H3z"/><path d="M3 13h18v4H3z"/></svg>
    		</span>
    		<span>Tickets</span>
	      </a>
            @endif
            @if(Route::has('settings.payment'))
              <a href="{{ route('settings.payment') }}"
                 class="nav-item {{ request()->routeIs('settings.payment') ? 'nav-active' : '' }}">
                Payment Gateway
              </a>
            @endif

	    {{-- ===== ROLE USER (KHUSUS ADMIN) ===== --}}
            @role('admin')
              <div class="mt-2">
                <button type="button"
                        class="w-full flex items-center justify-between px-3 py-2 text-[var(--muted)]"
                        @click="open.roleuser = !open.roleuser">
                  <span class="text-xs tracking-wider">ROLE USER</span>
                  <span x-text="open.roleuser ? '▾' : '▸'"></span>
                </button>
                <div class="space-y-1 px-1" x-show="open.roleuser" x-collapse>
                  @if(Route::has('settings.roles'))
                    <a href="{{ route('settings.roles') }}"
                       class="nav-item {{ request()->routeIs('settings.roles') ? 'nav-active' : '' }}">
                      Kelola Role
                    </a>
                  @endif
                  <a href="{{ route('users.index') }}"
                     class="nav-item {{ request()->routeIs('users.index') ? 'nav-active' : '' }}">
                    Kelola User
                  </a>
                  <a href="{{ route('users.create') }}"
                     class="nav-item {{ request()->routeIs('users.create') ? 'nav-active' : '' }}">
                    Tambah User
                  </a>
            	  @if(Route::has('settings.permissions'))
              	  <a href="{{ route('settings.permissions') }}"
                     class="nav-item {{ request()->routeIs('settings.permissions') ? 'nav-active' : '' }}">
                    Permissions
                  </a>
                  @endif

                  @if(Route::has('logs.index'))
                    <a href="{{ route('logs.index') }}"
                       class="nav-item {{ request()->routeIs('logs.index') ? 'nav-active' : '' }}">
                      Audit Log
                    </a>

	      	    <a href="{{ route('wa.staff.index', [], false) }}" class="m-navlink {{ request()->is('wa/staff') ? 'active' : '' }}">
    	 	      <span class="m-navicon">
      		        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11z"/><path d="M8 13c-2.67 0-8 1.34-8 4v3h10M16 13c.29 0 .62.02.97.05C20.18 13.36 24 14.72 24 17v3h-8"/></svg>
    		      </span>
    		      <span>WA Staff</span>
  	      	    </a>
                  @endif
                </div>
              </div>
            @endrole
            {{-- ===== /ROLE USER ===== --}}
          </div>
        </div>
      @endcan

      {{-- LOGOUT --}}
      <form method="POST" action="{{ route('logout') }}" class="px-2">
        @csrf
        <button class="w-full nav-item text-left text-red-300">Logout</button>
      </form>
    </nav>
  </aside>

  {{-- ========== MAIN ========== --}}
  <main class="flex-1 lg:ml-0">
    <header class="topbar text-white px-4 lg:px-6 py-3 lg:py-4 shadow">
      <div class="max-w-7xl mx-auto flex items-center gap-3 justify-between">
        <div class="flex items-center gap-3">
          {{-- hamburger (hanya mobile) --}}
          <button class="lg:hidden m-btn m-btn-outline m-btn-sm" @click="openSidebar=true" title="Menu">
            ☰
          </button>
          <div class="font-semibold text-base lg:text-lg">@yield('title','Overview')</div>
        </div>
        <div class="text-sm opacity-80 truncate max-w-[50%] text-right">{{ auth()->user()->name ?? '' }}</div>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-4 lg:py-6">
      @yield('content')
    </div>
  </main>
</div>

<script src="{{ asset('js/mon-groups-v1.js') }}?v=1" defer></script>
<script src="{{ asset('js/pppoe-ui-v2.js') }}?v=1" defer></script>
<script src="{{ asset('js/mon-ui-tweak-v3.js') }}?v=1" defer></script>
@stack('scripts')
</body>
</html>
