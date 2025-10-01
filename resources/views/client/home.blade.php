<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Client Home</title>
  <link rel="stylesheet" href="{{ asset('assets/client-auth.css') }}">
</head>
<body class="auth-bg">
  <div class="auth-wrap" style="max-width:720px">
    <div class="card">
      <div class="card-title" style="text-align:center">Selamat datang, {{ auth()->user()->name ?? 'User' }}</div>
      <p style="color:#a7c6ff; text-align:center">Ini adalah halaman contoh. Nanti kita isi Dashboard (status koneksi, paket, kuota, tombol cepat).</p>
      <form method="POST" action="{{ route('client.logout') }}" style="text-align:center; margin-top:14px">
        @csrf
        <button class="btn" type="submit" style="max-width:220px">Keluar</button>
      </form>
    </div>
  </div>
</body>
</html>
