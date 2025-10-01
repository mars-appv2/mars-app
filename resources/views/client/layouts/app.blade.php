<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Client')</title>
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
        <div class="sub">TELEKOMUNIKASI</div>
      </div>
    </div>

    <nav class="nav">
      <a href="{{ route('client.dashboard') }}" class="navlink {{ request()->routeIs('client.dashboard')?'active':'' }}">Dashboard</a>
      <a href="{{ route('client.traffic') }}" class="navlink {{ request()->routeIs('client.traffic')?'active':'' }}">Traffic</a>
      <a href="{{ route('client.invoices') }}" class="navlink {{ request()->routeIs('client.invoices')?'active':'' }}">Invoices</a>
      <a href="{{ route('client.wifi') }}" class="navlink {{ request()->routeIs('client.wifi')?'active':'' }}">WiFi/SSID</a>
    </nav>

    <form method="POST" action="{{ route('client.logout') }}">
      @csrf
      <button class="btn btn-sm" type="submit">Logout</button>
    </form>
  </div>
</header>

<main class="container">
  @yield('content')
</main>

<footer class="foot">© {{ date('Y') }} PT MARS DATA TELEKOMUNIKASI</footer>

@yield('scripts')
</body>
</html>
