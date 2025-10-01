<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Staff')</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/client-app.css') }}">
</head>
<body class="bg">
<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <img src="{{ asset('images/md-logo.svg') }}" class="logo" alt="logo">
      <div>
        <div class="ttl">MARS DATA</div>
        <div class="sub">STAFF PORTAL</div>
      </div>
    </div>

@php
    $u = auth()->user();
    $hasRole = function(string $role) use ($u) {
        if (!$u) return false;
        if (method_exists($u,'hasRole')) return $u->hasRole($role);
        if (isset($u->role)) return strtolower($u->role) === strtolower($role);
        if (method_exists($u,'roles')) return $u->roles()->where('name',$role)->exists();
        return false;
    };
    $isAdmin    = $hasRole('admin');
    $isOperator = $hasRole('operator');
    $isStaff    = $hasRole('staff');
    $isTeknisi  = $hasRole('teknisi');
@endphp

    <nav class="nav">
      @if($isAdmin)
        <a href="{{ route('staff.dashboard') }}" class="navlink {{ request()->routeIs('staff.dashboard')?'active':'' }}">Dashboard</a>
        <a href="{{ route('staff.noc') }}"       class="navlink {{ request()->routeIs('staff.noc*')?'active':'' }}">NOC</a>
        <a href="{{ route('staff.tickets') }}"   class="navlink {{ request()->routeIs('staff.tickets')?'active':'' }}">Tickets</a>
        <a href="{{ route('staff.billing') }}"   class="navlink {{ request()->routeIs('staff.billing')?'active':'' }}">Billing</a>
        @if(Route::has('staff.customers.index'))
          <a href="{{ route('staff.customers.index') }}" class="navlink {{ request()->routeIs('staff.customers.*') && !request()->routeIs('staff.customers.create') ? 'active' : '' }}">Customers</a>
        @endif
        @if(Route::has('staff.customers.create'))
          <a href="{{ route('staff.customers.create') }}" class="navlink {{ request()->routeIs('staff.customers.create')?'active':'' }}">Create Customer</a>
        @endif
        @if(Route::has('staff.users.index'))
          <a href="{{ route('staff.users.index') }}" class="navlink {{ request()->routeIs('staff.users.*')?'active':'' }}">Staff</a>
        @endif

      @elseif($isOperator)
        <a href="{{ route('staff.dashboard') }}" class="navlink {{ request()->routeIs('staff.dashboard')?'active':'' }}">Dashboard</a>
        <a href="{{ route('staff.noc') }}"       class="navlink {{ request()->routeIs('staff.noc*')?'active':'' }}">NOC</a>
        <a href="{{ route('staff.tickets') }}"   class="navlink {{ request()->routeIs('staff.tickets')?'active':'' }}">Tickets</a>
        <a href="{{ route('staff.billing') }}"   class="navlink {{ request()->routeIs('staff.billing')?'active':'' }}">Billing</a>
        @if(Route::has('staff.customers.index'))
          <a href="{{ route('staff.customers.index') }}" class="navlink {{ request()->routeIs('staff.customers.*') && !request()->routeIs('staff.customers.create') ? 'active' : '' }}">Customers</a>
        @endif
        @if(Route::has('staff.customers.create'))
          <a href="{{ route('staff.customers.create') }}" class="navlink {{ request()->routeIs('staff.customers.create')?'active':'' }}">Create Customer</a>
        @endif

      @elseif($isStaff)
        <a href="{{ route('staff.tickets') }}"   class="navlink {{ request()->routeIs('staff.tickets')?'active':'' }}">Tickets</a>
        @if(Route::has('staff.customers.create'))
          <a href="{{ route('staff.customers.create') }}" class="navlink {{ request()->routeIs('staff.customers.create')?'active':'' }}">Create Customer</a>
        @endif
        <a href="{{ route('staff.billing') }}"   class="navlink {{ request()->routeIs('staff.billing')?'active':'' }}">Billing</a>

      @elseif($isTeknisi)
        <a href="{{ route('staff.tickets') }}" class="navlink {{ request()->routeIs('staff.tickets')?'active':'' }}">Tickets</a>
        @if(Route::has('staff.customers.index'))
          <a href="{{ route('staff.customers.index') }}" class="navlink {{ request()->routeIs('staff.customers.*') ? 'active' : '' }}">Customers</a>
        @endif
	<a href="{{ route('staff.devices.index') }}" class="navlink {{ request()->routeIs('staff.devices.*')?'active':'' }}">Perangkat</a>
      @else
        <a href="{{ route('staff.dashboard') }}" class="navlink {{ request()->routeIs('staff.dashboard')?'active':'' }}">Dashboard</a>
      @endif
    </nav>

    <form method="POST" action="{{ route('staff.logout') }}">
      @csrf
      <button class="btn btn-sm" type="submit">Logout</button>
    </form>
  </div>
</header>

<main class="container">@yield('content')</main>
<footer class="foot">© {{ date('Y') }} PT MARS DATA TELEKOMUNIKASI</footer>

@yield('scripts')
</body>
</html>
